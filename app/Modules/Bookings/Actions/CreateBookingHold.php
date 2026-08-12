<?php

namespace App\Modules\Bookings\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Availability\Services\AvailabilityService;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Exceptions\BookingHoldUnavailable;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Pricing\Services\PricingService;
use App\Modules\Settlements\PlatformCommissionSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateBookingHold
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly PricingService $pricingService,
        private readonly ExpireBookingHolds $expireBookingHolds,
        private readonly ResolveCancellationPolicySnapshot $resolveCancellationPolicySnapshot,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly PlatformCommissionSettings $platformCommissionSettings,
    ) {}

    /**
     * @param  array<string, mixed>  $customer
     */
    public function handle(
        FishingSpot $spot,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        array $customer,
        ?User $user = null
    ): Booking {
        $startsAt = CarbonImmutable::instance($startsAt);
        $endsAt = CarbonImmutable::instance($endsAt);

        try {
            return DB::transaction(function () use ($customer, $endsAt, $spot, $startsAt, $user): Booking {
                $lockedSpot = FishingSpot::query()
                    ->whereKey($spot->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedSpot->load('venue.organization');

                $venue = $lockedSpot->venue;
                assert($venue instanceof FishingVenue);

                $organization = $venue->organization;
                assert($organization instanceof Organization);

                if ($venue->commercial_status !== FishingVenueCommercialStatus::Bookable) {
                    throw new BookingHoldUnavailable('venue_not_bookable');
                }

                $this->expireBookingHolds->handle($lockedSpot);

                $availability = $this->availabilityService->check($lockedSpot, $startsAt, $endsAt);

                if (! $availability->available) {
                    throw new BookingHoldUnavailable($availability->reason ?? 'unavailable');
                }

                $quote = $this->pricingService->quote($lockedSpot, $startsAt, $endsAt);
                $commissionBps = $this->platformCommissionSettings->defaultCommissionBps();
                $platformFeeAmount = intdiv($quote->totalAmount * $commissionBps, 10000);

                $booking = Booking::create([
                    'user_id' => $user?->id,
                    'organization_id' => $organization->id,
                    'fishing_venue_id' => $venue->id,
                    'status' => BookingStatus::PaymentPending,
                    'currency' => $quote->currency,
                    'subtotal_amount' => $quote->totalAmount,
                    'discount_amount' => 0,
                    'total_amount' => $quote->totalAmount,
                    'commission_bps' => $commissionBps,
                    'platform_fee_amount' => $platformFeeAmount,
                    'operator_amount' => $quote->totalAmount - $platformFeeAmount,
                    'customer_name' => $customer['customer_name'],
                    'customer_email' => $customer['customer_email'],
                    'customer_phone' => $customer['customer_phone'] ?? null,
                    'cancellation_policy_snapshot' => $this->resolveCancellationPolicySnapshot->handle($organization),
                ]);

                $reservation = $booking->spotReservations()->create([
                    'fishing_spot_id' => $lockedSpot->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => ReservationStatus::Hold,
                    'hold_expires_at' => now()->addMinutes(10),
                ]);

                $booking->priceLines()->create([
                    'type' => BookingPriceLineType::Spot,
                    'description' => "{$lockedSpot->name} x {$quote->chargedDays} dni",
                    'quantity' => $quote->chargedDays,
                    'unit_amount' => $quote->unitAmount,
                    'total_amount' => $quote->totalAmount,
                ]);

                $this->recordAuditLog->handle(
                    action: 'booking.hold_created',
                    subject: $booking,
                    actor: $user,
                    organization: $organization,
                    after: [
                        'booking_uuid' => $booking->uuid,
                        'booking_status' => $booking->status->value,
                        'reservation_uuid' => $reservation->uuid,
                        'reservation_status' => $reservation->status->value,
                        'spot_uuid' => $lockedSpot->uuid,
                        'starts_at' => $startsAt->toIso8601String(),
                        'ends_at' => $endsAt->toIso8601String(),
                        'total_amount' => $booking->total_amount,
                        'currency' => $booking->currency,
                    ],
                );

                return $booking->load(['priceLines', 'spotReservations.spot']);
            });
        } catch (QueryException $exception) {
            if ($this->isReservationOverlapConstraintViolation($exception)) {
                throw new BookingHoldUnavailable('reserved');
            }

            throw $exception;
        }
    }

    private function isReservationOverlapConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = $exception->getMessage();

        return $sqlState === '23P01'
            || str_contains($message, 'spot_reservations_no_active_overlap');
    }
}
