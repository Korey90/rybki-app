<?php

use App\Models\User;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Payments\Actions\ReconcilePayments;
use App\Modules\Payments\Contracts\PayUMarketplaceClient;
use App\Modules\Payments\Contracts\StripeConnectClient;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentReconciliationItemStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\ProviderAccountOnboardingStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentProviderSetting;
use App\Modules\Payments\Models\PaymentReconciliationItem;
use App\Modules\Payments\Models\PaymentWebhookEvent;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Payments\Models\Refund;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function (): void {
    Carbon::setTestNow();
});

test('admin can switch global marketplace payment provider', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.payment-providers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payment-providers/index')
            ->where('active_provider', PaymentProvider::Test->value)
            ->where('providers.0.value', PaymentProvider::Test->value)
            ->where('providers.1.value', PaymentProvider::StripeConnect->value)
            ->where('providers.2.value', PaymentProvider::PayUMarketplace->value));

    $this->actingAs($admin)
        ->patch(route('admin.payment-providers.update'), [
            'active_provider' => PaymentProvider::StripeConnect->value,
        ])
        ->assertRedirect();

    expect(PaymentProviderSetting::query()->firstOrFail()->active_provider)
        ->toBe(PaymentProvider::StripeConnect);
});

test('stripe connect checkout session and signed webhook confirm booking once', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    config(['services.stripe_connect.webhook_secret' => 'whsec_phase16']);
    app()->instance(StripeConnectClient::class, new Phase16FakeStripeClient);
    phase16UseProvider(PaymentProvider::StripeConnect);

    $angler = User::factory()->create();
    $booking = phase16BookingHold(PaymentProvider::StripeConnect, $angler);

    $response = $this->actingAs($angler)
        ->postJson(route('api.bookings.payment.store', $booking));

    $response
        ->assertCreated()
        ->assertJsonPath('payment_session.provider', PaymentProvider::StripeConnect->value)
        ->assertJsonPath('payment_session.provider_payment_id', 'cs_phase16')
        ->assertJsonPath('payment_session.checkout_url', 'https://checkout.stripe.test/cs_phase16');

    $payment = Payment::query()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Processing)
        ->and($payment->checkout_url)->toBe('https://checkout.stripe.test/cs_phase16');

    $payload = phase16StripePayload('checkout.session.completed', 'cs_phase16');
    $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = phase16StripeSignature($rawPayload, 'whsec_phase16');

    $this->call('POST', route('api.webhooks.stripe-connect'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $rawPayload)
        ->assertOk()
        ->assertJsonPath('webhook_event.provider', PaymentProvider::StripeConnect->value)
        ->assertJsonPath('webhook_event.error', null);

    $this->call('POST', route('api.webhooks.stripe-connect'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $rawPayload)
        ->assertOk();

    $booking->refresh();
    $payment->refresh();

    expect(PaymentWebhookEvent::query()->count())->toBe(1)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->provider_payload)->not->toBeNull();
});

test('stripe connect rejects invalid signature and failed payment does not confirm booking', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    config(['services.stripe_connect.webhook_secret' => 'whsec_phase16']);
    app()->instance(StripeConnectClient::class, new Phase16FakeStripeClient);
    phase16UseProvider(PaymentProvider::StripeConnect);

    $booking = phase16BookingHold(PaymentProvider::StripeConnect);

    $this->postJson(route('api.bookings.payment.store', $booking))->assertCreated();

    $payload = phase16StripePayload('checkout.session.async_payment_failed', 'cs_phase16');
    $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call('POST', route('api.webhooks.stripe-connect'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.now()->timestamp.',v1=invalid',
    ], $rawPayload)
        ->assertBadRequest()
        ->assertJsonPath('reason', 'stripe_signature_mismatch');

    $this->call('POST', route('api.webhooks.stripe-connect'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => phase16StripeSignature($rawPayload, 'whsec_phase16'),
    ], $rawPayload)
        ->assertOk();

    $booking->refresh();
    $payment = Payment::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::PaymentPending)
        ->and($payment->status)->toBe(PaymentStatus::Failed);
});

test('stripe connect refund is synced through provider during cancellation', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    config(['services.stripe_connect.webhook_secret' => 'whsec_phase16']);
    app()->instance(StripeConnectClient::class, new Phase16FakeStripeClient);

    $angler = User::factory()->create();
    $booking = phase16ConfirmedBooking(PaymentProvider::StripeConnect, $angler);

    $this->actingAs($angler)
        ->patch(route('account.bookings.cancel', $booking))
        ->assertRedirect();

    $payment = Payment::query()->firstOrFail();
    $refund = Refund::query()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_refund_id)->toBe('re_phase16');
});

test('payu marketplace checkout session and signed notification confirm booking', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    config([
        'services.payu_marketplace.pos_id' => '300746',
        'services.payu_marketplace.client_id' => '300746',
        'services.payu_marketplace.client_secret' => 'secret',
        'services.payu_marketplace.second_key' => 'payu_second_key',
    ]);
    app()->instance(PayUMarketplaceClient::class, new Phase16FakePayUClient);
    phase16UseProvider(PaymentProvider::PayUMarketplace);

    $booking = phase16BookingHold(PaymentProvider::PayUMarketplace);

    $this->postJson(route('api.bookings.payment.store', $booking))
        ->assertCreated()
        ->assertJsonPath('payment_session.provider', PaymentProvider::PayUMarketplace->value)
        ->assertJsonPath('payment_session.provider_payment_id', 'payu_order_phase16')
        ->assertJsonPath('payment_session.checkout_url', 'https://payu.test/order/payu_order_phase16');

    $payload = phase16PayUPayload('payu_order_phase16', 'COMPLETED');
    $rawPayload = json_encode($payload, JSON_THROW_ON_ERROR);

    $this->call('POST', route('api.webhooks.payu-marketplace'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_OPENPAYU_SIGNATURE' => phase16PayUSignature($rawPayload, 'payu_second_key'),
    ], $rawPayload)
        ->assertOk()
        ->assertJsonPath('webhook_event.provider', PaymentProvider::PayUMarketplace->value);

    $booking->refresh();
    $payment = Payment::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($payment->status)->toBe(PaymentStatus::Succeeded);
});

test('payu marketplace refund is synced through provider during cancellation', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    config([
        'services.payu_marketplace.pos_id' => '300746',
        'services.payu_marketplace.client_id' => '300746',
        'services.payu_marketplace.client_secret' => 'secret',
        'services.payu_marketplace.second_key' => 'payu_second_key',
    ]);
    app()->instance(PayUMarketplaceClient::class, new Phase16FakePayUClient);

    $angler = User::factory()->create();
    $booking = phase16ConfirmedBooking(PaymentProvider::PayUMarketplace, $angler);

    $this->actingAs($angler)
        ->patch(route('account.bookings.cancel', $booking))
        ->assertRedirect();

    $payment = Payment::query()->firstOrFail();
    $refund = Refund::query()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->provider_refund_id)->toBe('payu_refund_phase16');
});

test('reconciliation compares local payment with provider api status', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    config(['services.stripe_connect.webhook_secret' => 'whsec_phase16']);
    $fakeClient = new Phase16FakeStripeClient;
    $fakeClient->paymentStatus = 'paid';
    app()->instance(StripeConnectClient::class, $fakeClient);

    $booking = phase16BookingHold(PaymentProvider::StripeConnect);
    $payment = $booking->payments()->create([
        'provider' => PaymentProvider::StripeConnect,
        'provider_payment_id' => 'cs_phase16',
        'checkout_url' => 'https://checkout.stripe.test/cs_phase16',
        'status' => PaymentStatus::Processing,
        'amount' => $booking->total_amount,
        'currency' => $booking->currency,
        'idempotency_key' => 'phase16-reconcile-'.Str::uuid(),
    ]);
    $payment->forceFill([
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ])->save();

    app(ReconcilePayments::class)
        ->handle(PaymentProvider::StripeConnect->value, 30);

    $item = PaymentReconciliationItem::query()->where('payment_id', $payment->id)->firstOrFail();

    expect($item->status)->toBe(PaymentReconciliationItemStatus::Mismatch)
        ->and($item->reason)->toBe('provider_status_differs_from_local_payment')
        ->and($item->details['provider_status'])->toBe(PaymentStatus::Succeeded->value);
});

function phase16UseProvider(PaymentProvider $provider): void
{
    PaymentProviderSetting::query()->updateOrCreate(
        ['scope' => PaymentProviderSetting::GLOBAL_SCOPE],
        ['active_provider' => $provider],
    );
}

function phase16BookingHold(PaymentProvider $provider, ?User $user = null): Booking
{
    [, $spot] = phase16BookableSpot($provider);

    $request = test();

    if ($user instanceof User) {
        $request = $request->actingAs($user);
    }

    $request->postJson(route('api.booking-holds.store'), phase16HoldPayload($spot))
        ->assertCreated();

    return Booking::query()->with('spotReservations')->latest('id')->firstOrFail();
}

function phase16ConfirmedBooking(PaymentProvider $provider, User $angler): Booking
{
    $booking = phase16BookingHold($provider, $angler);

    $booking->update([
        'status' => BookingStatus::Confirmed,
        'confirmed_at' => now(),
    ]);

    $booking->spotReservations()->update([
        'status' => ReservationStatus::Confirmed,
        'hold_expires_at' => null,
    ]);

    $booking->payments()->create([
        'provider' => $provider,
        'provider_payment_id' => $provider === PaymentProvider::StripeConnect ? 'cs_phase16' : 'payu_order_phase16',
        'checkout_url' => 'https://provider.test/checkout',
        'provider_payload' => [
            'payment_intent' => 'pi_phase16',
        ],
        'status' => PaymentStatus::Succeeded,
        'amount' => $booking->total_amount,
        'currency' => $booking->currency,
        'idempotency_key' => 'phase16-confirmed-'.Str::uuid(),
        'paid_at' => now(),
    ]);

    return $booking->refresh();
}

/**
 * @return array{FishingVenue, FishingSpot}
 */
function phase16BookableSpot(PaymentProvider $provider): array
{
    $venue = FishingVenue::factory()->create();

    ProviderAccount::create([
        'organization_id' => $venue->organization_id,
        'provider' => $provider,
        'external_account_id' => match ($provider) {
            PaymentProvider::StripeConnect => 'acct_phase16',
            PaymentProvider::PayUMarketplace => 'payu-submerchant-phase16',
            PaymentProvider::Test => 'test-account-phase16',
        },
        'onboarding_status' => ProviderAccountOnboardingStatus::Enabled,
        'payments_enabled' => true,
        'payouts_enabled' => true,
    ]);

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
 * @return array<string, string>
 */
function phase16HoldPayload(FishingSpot $spot): array
{
    return [
        'fishing_spot_uuid' => $spot->uuid,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
        'customer_phone' => '+48123123123',
    ];
}

/**
 * @return array<string, mixed>
 */
function phase16StripePayload(string $eventType, string $sessionId): array
{
    return [
        'id' => 'evt_phase16_'.$eventType,
        'type' => $eventType,
        'data' => [
            'object' => [
                'id' => $sessionId,
                'object' => 'checkout.session',
                'status' => $eventType === 'checkout.session.async_payment_failed' ? 'complete' : 'complete',
                'payment_status' => $eventType === 'checkout.session.async_payment_failed' ? 'unpaid' : 'paid',
                'payment_intent' => 'pi_phase16',
            ],
        ],
    ];
}

function phase16StripeSignature(string $rawPayload, string $secret): string
{
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

/**
 * @return array<string, mixed>
 */
function phase16PayUPayload(string $orderId, string $status): array
{
    return [
        'order' => [
            'orderId' => $orderId,
            'extOrderId' => 'phase16-payment',
            'status' => $status,
            'currencyCode' => 'PLN',
            'totalAmount' => '10000',
        ],
        'properties' => [
            [
                'name' => 'PAYMENT_ID',
                'value' => 'payu-payment-phase16',
            ],
        ],
    ];
}

function phase16PayUSignature(string $rawPayload, string $secondKey): string
{
    return 'sender=checkout;signature='.md5($rawPayload.$secondKey).';algorithm=MD5;content=DOCUMENT';
}

final class Phase16FakeStripeClient implements StripeConnectClient
{
    public string $paymentStatus = 'unpaid';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $payload, string $idempotencyKey): array
    {
        return [
            'id' => 'cs_phase16',
            'url' => 'https://checkout.stripe.test/cs_phase16',
            'payment_status' => 'unpaid',
            'payment_intent' => 'pi_phase16',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        return [
            'id' => $sessionId,
            'url' => 'https://checkout.stripe.test/'.$sessionId,
            'status' => $this->paymentStatus === 'paid' ? 'complete' : 'open',
            'payment_status' => $this->paymentStatus,
            'payment_intent' => [
                'id' => 'pi_phase16',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRefund(array $payload, string $idempotencyKey): array
    {
        return [
            'id' => 're_phase16',
            'status' => 'succeeded',
            'payment_intent' => 'pi_phase16',
        ];
    }

    public function isConfigured(): bool
    {
        return true;
    }
}

final class Phase16FakePayUClient implements PayUMarketplaceClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, string $idempotencyKey): array
    {
        return [
            'orderId' => 'payu_order_phase16',
            'redirectUri' => 'https://payu.test/order/payu_order_phase16',
            'status' => [
                'statusCode' => 'SUCCESS',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveOrder(string $orderId): array
    {
        return [
            'orders' => [
                [
                    'orderId' => $orderId,
                    'status' => 'COMPLETED',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRefund(string $orderId, array $payload, string $idempotencyKey): array
    {
        return [
            'orderId' => $orderId,
            'refund' => [
                'refundId' => 'payu_refund_phase16',
                'status' => 'FINALIZED',
            ],
        ];
    }

    public function isConfigured(): bool
    {
        return true;
    }
}
