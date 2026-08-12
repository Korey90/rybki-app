<?php

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('angler can cancel a confirmed booking before free cancellation deadline with full logical refund', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    $angler = User::factory()->create();
    $booking = phase11ConfirmedBooking($angler, startsAt: '2026-08-14 10:00:00', freeCancellationHours: 24);

    $this->actingAs($angler)
        ->get(route('account.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.cancellation.can_cancel', true)
            ->where('booking.cancellation.refund_amount', 10000)
            ->where('booking.cancellation_policy.free_cancellation_hours', 24));

    $this->actingAs($angler)
        ->patch(route('account.bookings.cancel', $booking))
        ->assertRedirect();

    $booking->refresh();
    $payment = Payment::query()->firstOrFail();
    $refund = Refund::query()->firstOrFail();
    $reservation = SpotReservation::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Refunded)
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($reservation->status)->toBe(ReservationStatus::Cancelled)
        ->and($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($refund->amount)->toBe(10000)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_refund_id)->toBeNull()
        ->and($refund->initiated_by_user_id)->toBe($angler->id)
        ->and(AuditLog::query()->where('action', 'booking.cancelled')->exists())->toBeTrue();
});

test('angler cancellation after free cancellation deadline stores partial logical refund', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    $angler = User::factory()->create();
    $booking = phase11ConfirmedBooking(
        $angler,
        startsAt: '2026-08-12 12:00:00',
        freeCancellationHours: 48,
        lateRefundPercentage: 25,
    );

    $this->actingAs($angler)
        ->patch(route('account.bookings.cancel', $booking))
        ->assertRedirect();

    $booking->refresh();
    $payment = Payment::query()->firstOrFail();
    $refund = Refund::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::PartiallyRefunded)
        ->and($payment->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($refund->amount)->toBe(2500)
        ->and($refund->reason)->toBe('Anulowanie przez wedkarza');
});

test('angler can cancel an unpaid booking hold without creating a refund', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    $angler = User::factory()->create();
    $booking = phase11Booking($angler, BookingStatus::PaymentPending, ReservationStatus::Hold, startsAt: '2026-08-12 12:00:00');

    $this->actingAs($angler)
        ->patch(route('account.bookings.cancel', $booking))
        ->assertRedirect();

    $booking->refresh();
    $reservation = SpotReservation::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($reservation->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->hold_expires_at)->toBeNull()
        ->and(Refund::query()->count())->toBe(0);
});

test('booking cannot be cancelled after reservation starts', function () {
    Carbon::setTestNow('2026-08-12 12:30:00');
    $angler = User::factory()->create();
    $booking = phase11ConfirmedBooking($angler, startsAt: '2026-08-12 12:00:00');

    $this->actingAs($angler)
        ->patch(route('account.bookings.cancel', $booking))
        ->assertSessionHasErrors('cancellation');

    $booking->refresh();
    $reservation = SpotReservation::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and(Refund::query()->count())->toBe(0);
});

test('operator can cancel a manageable booking and see refund details', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    $angler = User::factory()->create();
    $operator = User::factory()->create();
    $booking = phase11ConfirmedBooking($angler, startsAt: '2026-08-14 10:00:00');

    OrganizationMember::create([
        'organization_id' => $booking->organization_id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($operator)
        ->patch(route('operator.bookings.cancel', $booking), [
            'reason' => 'Zamknięcie łowiska.',
        ])
        ->assertRedirect();

    $booking->refresh();
    $refund = Refund::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Refunded)
        ->and($refund->amount)->toBe(10000)
        ->and($refund->reason)->toBe('Zamknięcie łowiska.')
        ->and($refund->initiated_by_user_id)->toBe($operator->id);

    $this->actingAs($operator)
        ->get(route('operator.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.cancelled_at', fn (?string $value): bool => $value !== null)
            ->where('booking.refunds.0.amount', 10000)
            ->where('booking.refunds.0.initiated_by.email', $operator->email)
            ->where('booking.cancellation.can_cancel', false));
});

test('operator cannot cancel booking from a foreign organization', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    $angler = User::factory()->create();
    $operator = User::factory()->create();
    $booking = phase11ConfirmedBooking($angler, startsAt: '2026-08-14 10:00:00');

    OrganizationMember::create([
        'organization_id' => Organization::factory()->create()->id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($operator)
        ->patch(route('operator.bookings.cancel', $booking))
        ->assertForbidden();
});

function phase11ConfirmedBooking(
    User $angler,
    string $startsAt,
    int $freeCancellationHours = 24,
    int $lateRefundPercentage = 0,
): Booking {
    $booking = phase11Booking(
        $angler,
        BookingStatus::Confirmed,
        ReservationStatus::Confirmed,
        $startsAt,
        freeCancellationHours: $freeCancellationHours,
        lateRefundPercentage: $lateRefundPercentage,
    );

    $booking->payments()->create([
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'test_pay_'.Str::uuid(),
        'status' => PaymentStatus::Succeeded,
        'amount' => 10000,
        'currency' => 'PLN',
        'idempotency_key' => 'phase11-payment-'.Str::uuid(),
        'paid_at' => now(),
    ]);

    return $booking;
}

function phase11Booking(
    User $angler,
    BookingStatus $bookingStatus,
    ReservationStatus $reservationStatus,
    string $startsAt,
    int $freeCancellationHours = 24,
    int $lateRefundPercentage = 0,
): Booking {
    [$venue, $spot] = phase11VenueWithSpot();
    $endsAt = Carbon::parse($startsAt)->addHours(2);

    $booking = Booking::create([
        'user_id' => $angler->id,
        'organization_id' => $venue->organization_id,
        'fishing_venue_id' => $venue->id,
        'status' => $bookingStatus,
        'currency' => 'PLN',
        'subtotal_amount' => 10000,
        'discount_amount' => 0,
        'total_amount' => 10000,
        'commission_bps' => 0,
        'platform_fee_amount' => 0,
        'operator_amount' => 10000,
        'customer_name' => $angler->name,
        'customer_email' => $angler->email,
        'cancellation_policy_snapshot' => [
            'policy_id' => null,
            'name' => 'Testowa polityka',
            'free_cancellation_hours' => $freeCancellationHours,
            'late_refund_percentage' => $lateRefundPercentage,
        ],
        'confirmed_at' => $bookingStatus === BookingStatus::Confirmed ? now() : null,
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'status' => $reservationStatus,
        'hold_expires_at' => $reservationStatus === ReservationStatus::Hold ? now()->addMinutes(10) : null,
    ]);

    $booking->priceLines()->create([
        'type' => BookingPriceLineType::Spot,
        'description' => 'Stanowisko 1 x 1 dni',
        'quantity' => 1,
        'unit_amount' => 10000,
        'total_amount' => 10000,
    ]);

    return $booking;
}

/**
 * @return array{FishingVenue, FishingSpot}
 */
function phase11VenueWithSpot(): array
{
    $venue = FishingVenue::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => 'Lowisko Anulowanie '.Str::uuid(),
        'slug' => 'lowisko-anulowanie-'.Str::uuid(),
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
    ]);

    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'base_price_amount' => 10000,
        'currency' => 'PLN',
    ]);

    return [$venue, $spot];
}
