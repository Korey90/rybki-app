<?php

use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('customers can create test payment sessions for booking holds', function () {
    [, , $booking] = createPaymentFlowHold();

    $response = $this->postJson(route('api.bookings.payment.store', $booking), [
        'provider' => PaymentProvider::Test->value,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('payment_session.provider', PaymentProvider::Test->value)
        ->assertJsonPath('payment_session.amount', 10000)
        ->assertJsonPath('payment_session.currency', 'PLN');

    expect(Payment::query()->count())->toBe(1);

    $payment = Payment::query()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Created)
        ->and($payment->amount)->toBe(10000)
        ->and($payment->provider_payment_id)->toBe($response->json('payment_session.provider_payment_id'));
});

test('test payment succeeded webhook confirms booking and reservation', function () {
    [, , $booking] = createPaymentFlowHold();
    $providerPaymentId = createPaymentFlowSession($booking);

    $this->postJson(route('api.webhooks.test-payment'), paymentFlowWebhookPayload($providerPaymentId))
        ->assertOk()
        ->assertJsonPath('webhook_event.provider', PaymentProvider::Test->value)
        ->assertJsonPath('webhook_event.error', null);

    $booking->refresh();
    $reservation = $booking->spotReservations()->firstOrFail();
    $payment = $booking->payments()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->confirmed_at)->not->toBeNull()
        ->and($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->hold_expires_at)->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->paid_at)->not->toBeNull()
        ->and(PaymentWebhookEvent::query()->whereNotNull('processed_at')->count())->toBe(1);
});

test('duplicate test payment webhooks are processed once', function () {
    [, , $booking] = createPaymentFlowHold();
    $providerPaymentId = createPaymentFlowSession($booking);
    $payload = paymentFlowWebhookPayload($providerPaymentId, [
        'provider_event_id' => 'evt_duplicate_payment',
    ]);

    $this->postJson(route('api.webhooks.test-payment'), $payload)->assertOk();
    $this->postJson(route('api.webhooks.test-payment'), $payload)->assertOk();

    $booking->refresh();

    expect(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->payments()->where('status', PaymentStatus::Succeeded->value)->count())->toBe(1);
});

test('succeeded payment after expired hold does not confirm booking', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    [, , $booking] = createPaymentFlowHold();
    $providerPaymentId = createPaymentFlowSession($booking);

    Carbon::setTestNow('2026-08-11 12:11:00');

    $this->postJson(route('api.webhooks.test-payment'), paymentFlowWebhookPayload($providerPaymentId))
        ->assertConflict()
        ->assertJsonPath('reason', 'hold_expired');

    $booking->refresh();
    $reservation = $booking->spotReservations()->firstOrFail();
    $payment = $booking->payments()->firstOrFail();
    $webhookEvent = PaymentWebhookEvent::query()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($booking->status)->toBe(BookingStatus::PaymentPending)
        ->and($reservation->status)->toBe(ReservationStatus::Expired)
        ->and($webhookEvent->processed_at)->toBeNull()
        ->and($webhookEvent->failed_at)->not->toBeNull()
        ->and($webhookEvent->error)->toBe('hold_expired');

    Carbon::setTestNow();
});

test('payment session is rejected after hold expires', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    [, , $booking] = createPaymentFlowHold();

    Carbon::setTestNow('2026-08-11 12:11:00');

    $this->postJson(route('api.bookings.payment.store', $booking), [
        'provider' => PaymentProvider::Test->value,
    ])
        ->assertConflict()
        ->assertJsonPath('reason', 'hold_expired');

    expect(Payment::query()->count())->toBe(0)
        ->and($booking->spotReservations()->firstOrFail()->status)->toBe(ReservationStatus::Expired);

    Carbon::setTestNow();
});

/**
 * @return array{FishingVenue, FishingSpot, Booking}
 */
function createPaymentFlowHold(): array
{
    [$venue, $spot] = paymentFlowBookableSpot();

    test()->postJson(route('api.booking-holds.store'), paymentFlowHoldPayload($spot))
        ->assertCreated();

    return [$venue, $spot, Booking::query()->with('spotReservations')->firstOrFail()];
}

/**
 * @return array{FishingVenue, FishingSpot}
 */
function paymentFlowBookableSpot(): array
{
    $venue = FishingVenue::factory()->create();
    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'base_price_amount' => 10000,
        'currency' => 'PLN',
    ]);

    $spot->availabilityRules()->create([
        'day_of_week' => 3,
        'opens_at' => '08:00',
        'closes_at' => '18:00',
        'minimum_booking_minutes' => 60,
        'maximum_booking_minutes' => 480,
    ]);

    return [$venue, $spot];
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function paymentFlowHoldPayload(FishingSpot $spot, array $overrides = []): array
{
    return [
        'fishing_spot_uuid' => $spot->uuid,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
        'customer_phone' => '+48123123123',
        ...$overrides,
    ];
}

function createPaymentFlowSession(Booking $booking): string
{
    $response = test()->postJson(route('api.bookings.payment.store', $booking), [
        'provider' => PaymentProvider::Test->value,
    ]);

    $response->assertCreated();

    return (string) $response->json('payment_session.provider_payment_id');
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function paymentFlowWebhookPayload(string $providerPaymentId, array $overrides = []): array
{
    return [
        'provider_event_id' => 'evt_'.str_replace('-', '_', (string) Str::uuid()),
        'event_type' => 'payment.succeeded',
        'provider_payment_id' => $providerPaymentId,
        'status' => PaymentStatus::Succeeded->value,
        ...$overrides,
    ];
}
