<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Bookings\Actions\CancelBooking;
use App\Modules\Bookings\Actions\ResolveBookingCancellationQuote;
use App\Modules\Bookings\DTO\BookingCancellationQuote;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Exceptions\BookingCancellationUnavailable;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\BookingPriceLine;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Queries\ManageableOrganizations;
use App\Modules\Payments\Exceptions\RefundFailed;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(
        private readonly ManageableOrganizations $manageableOrganizations,
        private readonly ResolveBookingCancellationQuote $resolveBookingCancellationQuote,
    ) {}

    /**
     * Show bookings owned by the operator's organizations.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', Booking::class);

        $organizationIds = $this->manageableOrganizations->idsFor($user);
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
        ]);
        $status = Arr::get($validated, 'status');
        $allowedStatuses = array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::cases());

        if (! in_array($status, $allowedStatuses, true)) {
            $status = null;
        }

        $bookings = Booking::query()
            ->with(['venue', 'spotReservations.spot', 'payments' => fn ($query) => $query->latest()])
            ->whereIn('organization_id', $organizationIds)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->limit(50)
            ->get();

        $statusCounts = [];
        foreach (BookingStatus::cases() as $bookingStatus) {
            $statusCounts[$bookingStatus->value] = Booking::query()
                ->whereIn('organization_id', $organizationIds)
                ->where('status', $bookingStatus->value)
                ->count();
        }

        return Inertia::render('operator/bookings/index', [
            'bookings' => $bookings->map(fn (Booking $booking): array => $this->bookingListPayload($booking))->all(),
            'filters' => [
                'status' => $status,
            ],
            'status_counts' => $statusCounts,
        ]);
    }

    /**
     * Show a single booking owned by the operator's organizations.
     */
    public function show(Request $request, Booking $booking): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        Gate::authorize('viewAsOperator', $booking);

        $booking->load([
            'organization',
            'venue',
            'spotReservations.spot',
            'priceLines',
            'payments',
            'refunds.initiatedBy',
        ]);

        return Inertia::render('operator/bookings/show', [
            'booking' => $this->bookingDetailPayload($booking),
        ]);
    }

    /**
     * Cancel a booking owned by one of the operator's organizations.
     */
    public function cancel(Request $request, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        Gate::authorize('cancelAsOperator', $booking);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cancelBooking->handle(
                booking: $booking,
                actor: $user,
                actorType: CancelBooking::ACTOR_OPERATOR,
                reason: $validated['reason'] ?? null,
            );
        } catch (BookingCancellationUnavailable $exception) {
            return back()->withErrors([
                'cancellation' => $this->cancellationMessage($exception->reason),
            ]);
        } catch (RefundFailed $exception) {
            return back()->withErrors([
                'cancellation' => $this->refundMessage($exception->reason),
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Rezerwacja została anulowana.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingListPayload(Booking $booking): array
    {
        $venue = $booking->venue;
        assert($venue instanceof FishingVenue);

        $reservation = $booking->spotReservations->first();
        $payment = $booking->payments->first();

        return [
            'uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'total_amount' => $booking->total_amount,
            'operator_amount' => $booking->operator_amount,
            'platform_fee_amount' => $booking->platform_fee_amount,
            'currency' => $booking->currency,
            'created_at' => $booking->created_at?->toIso8601String(),
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'venue' => [
                'uuid' => $venue->uuid,
                'name' => $venue->name,
            ],
            'reservation' => $reservation instanceof SpotReservation ? $this->reservationPayload($reservation) : null,
            'latest_payment' => $payment instanceof Payment ? [
                'uuid' => $payment->uuid,
                'provider' => $payment->provider->value,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingDetailPayload(Booking $booking): array
    {
        $venue = $booking->venue;
        assert($venue instanceof FishingVenue);

        return [
            'uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'currency' => $booking->currency,
            'subtotal_amount' => $booking->subtotal_amount,
            'discount_amount' => $booking->discount_amount,
            'total_amount' => $booking->total_amount,
            'commission_bps' => $booking->commission_bps,
            'platform_fee_amount' => $booking->platform_fee_amount,
            'operator_amount' => $booking->operator_amount,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'created_at' => $booking->created_at?->toIso8601String(),
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'venue' => [
                'uuid' => $venue->uuid,
                'name' => $venue->name,
                'city' => $venue->city,
            ],
            'reservations' => $booking->spotReservations->map(fn (SpotReservation $reservation): array => $this->reservationPayload($reservation))->values()->all(),
            'price_lines' => $booking->priceLines->map(fn (BookingPriceLine $line): array => [
                'type' => $line->type->value,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_amount' => $line->unit_amount,
                'total_amount' => $line->total_amount,
            ])->values()->all(),
            'payments' => $booking->payments->map(fn (Payment $payment): array => [
                'uuid' => $payment->uuid,
                'provider' => $payment->provider->value,
                'provider_payment_id' => $payment->provider_payment_id,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'failed_at' => $payment->failed_at?->toIso8601String(),
                'created_at' => $payment->created_at?->toIso8601String(),
            ])->values()->all(),
            'refunds' => $booking->refunds->map(fn (Refund $refund): array => [
                'provider_refund_id' => $refund->provider_refund_id,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'reason' => $refund->reason,
                'status' => $refund->status->value,
                'created_at' => $refund->created_at?->toIso8601String(),
                'initiated_by' => $refund->initiatedBy === null ? null : [
                    'name' => $refund->initiatedBy->name,
                    'email' => $refund->initiatedBy->email,
                ],
            ])->values()->all(),
            'cancellation_policy' => $this->cancellationPolicyPayload($booking),
            'cancellation' => $this->cancellationQuotePayload($this->resolveBookingCancellationQuote->handle($booking)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationPayload(SpotReservation $reservation): array
    {
        $spot = $reservation->spot;
        assert($spot instanceof FishingSpot);

        return [
            'uuid' => $reservation->uuid,
            'status' => $reservation->status->value,
            'starts_at' => $reservation->starts_at->toIso8601String(),
            'ends_at' => $reservation->ends_at->toIso8601String(),
            'hold_expires_at' => $reservation->hold_expires_at?->toIso8601String(),
            'spot' => [
                'uuid' => $spot->uuid,
                'name' => $spot->name,
            ],
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function cancellationPolicyPayload(Booking $booking): array
    {
        $snapshot = $booking->cancellation_policy_snapshot ?? [];

        return [
            'name' => (string) ($snapshot['name'] ?? 'Standard MVP'),
            'free_cancellation_hours' => (int) ($snapshot['free_cancellation_hours'] ?? 24),
            'late_refund_percentage' => (int) ($snapshot['late_refund_percentage'] ?? 0),
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function cancellationQuotePayload(BookingCancellationQuote $quote): array
    {
        return [
            'can_cancel' => $quote->canCancel,
            'reason' => $quote->reason,
            'refund_amount' => $quote->refundAmount,
            'refund_percentage' => $quote->refundPercentage,
            'currency' => $quote->currency,
            'free_cancellation_until' => $quote->freeCancellationUntil?->toIso8601String(),
        ];
    }

    private function cancellationMessage(string $reason): string
    {
        return match ($reason) {
            'already_cancelled' => 'Ta rezerwacja jest już anulowana.',
            'after_start' => 'Nie można anulować rezerwacji po rozpoczęciu terminu.',
            'payment_failed' => 'Rezerwacja z nieudaną płatnością nie wymaga anulowania.',
            'missing_reservation' => 'Nie znaleziono terminu rezerwacji.',
            default => 'Nie można anulować tej rezerwacji.',
        };
    }

    private function refundMessage(string $reason): string
    {
        return match ($reason) {
            'provider_refund_failed' => 'Provider płatności odrzucił zwrot.',
            default => 'Nie udało się wykonać zwrotu u providera płatności.',
        };
    }
}
