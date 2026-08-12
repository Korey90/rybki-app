<?php

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Bookings\Actions\CreateBookingHold;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\CancellationPolicy;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Actions\ImportGovernmentVenueRecords;
use App\Modules\GovernmentData\Models\DataSource;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Payments\Actions\ReconcilePayments;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentReconciliationItemStatus;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentReconciliationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
    RateLimiter::clear('phase9-test');
});

test('admin can inspect audit logs and regular users cannot', function () {
    $admin = User::factory()->admin()->create();
    $regularUser = User::factory()->create();
    $actor = User::factory()->create();
    $organization = Organization::factory()->create();

    app(RecordAuditLog::class)->handle(
        action: 'phase9.test',
        actor: $actor,
        organization: $organization,
        after: ['ok' => true],
    );

    $this->actingAs($regularUser)
        ->get(route('admin.audit-logs.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit-logs/index')
            ->has('logs', 1)
            ->where('logs.0.action', 'phase9.test')
            ->where('logs.0.actor.email', $actor->email)
            ->where('logs.0.organization.name', $organization->name));
});

test('claim submissions are rate limited', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $source = phase9DataSource();
    app(ImportGovernmentVenueRecords::class)->handle($source, [
        phase9PublicVenueRecord('RL-001', 'Jezioro Limit'),
    ]);

    $venue = FishingVenue::query()->firstOrFail();

    $this->actingAs($user);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('marketplace.fishing-venues.claims.store', $venue->slug), [
            'organization_id' => $organization->id,
            'evidence' => "Dokument {$i}",
        ])->assertRedirect();
    }

    $this->post(route('marketplace.fishing-venues.claims.store', $venue->slug), [
        'organization_id' => $organization->id,
        'evidence' => 'Ponad limit.',
    ])->assertTooManyRequests();
});

test('booking hold stores cancellation policy snapshot and writes audit events', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    [$venue, $spot] = phase9BookableVenueWithSpot();

    CancellationPolicy::create([
        'organization_id' => $venue->organization_id,
        'name' => 'Elastyczna doba',
        'free_cancellation_hours' => 48,
        'late_refund_percentage' => 25,
        'is_default' => true,
    ]);

    $booking = app(CreateBookingHold::class)->handle(
        spot: $spot,
        startsAt: Carbon::parse('2026-08-12T10:00:00Z'),
        endsAt: Carbon::parse('2026-08-12T12:00:00Z'),
        customer: [
            'customer_name' => 'Jan Kowalski',
            'customer_email' => 'jan@example.com',
            'customer_phone' => null,
        ],
    );

    expect($booking->cancellation_policy_snapshot)->toMatchArray([
        'name' => 'Elastyczna doba',
        'free_cancellation_hours' => 48,
        'late_refund_percentage' => 25,
    ])
        ->and(AuditLog::query()->where('action', 'booking.hold_created')->exists())->toBeTrue();
});

test('payment reconciliation detects local payment and booking mismatches', function () {
    $booking = phase9Booking();
    $payment = Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'test_pay_'.Str::uuid(),
        'status' => PaymentStatus::Succeeded,
        'amount' => 10000,
        'currency' => 'PLN',
        'idempotency_key' => 'phase9-reconcile-'.Str::uuid(),
        'paid_at' => now(),
    ]);

    $run = app(ReconcilePayments::class)->handle(PaymentProvider::Test->value);
    $item = $run->items()->firstOrFail();

    expect($run->checked_count)->toBe(1)
        ->and($run->mismatch_count)->toBe(1)
        ->and($item->payment_id)->toBe($payment->id)
        ->and($item->status)->toBe(PaymentReconciliationItemStatus::Mismatch)
        ->and($item->reason)->toBe('succeeded_payment_without_confirmed_booking');
});

test('admin can run payment reconciliation from the panel', function () {
    $admin = User::factory()->admin()->create();
    phase9Booking();

    $this->actingAs($admin)
        ->post(route('admin.payment-reconciliation.store'), [
            'provider' => PaymentProvider::Test->value,
            'stale_minutes' => 30,
        ])
        ->assertRedirect();

    expect(PaymentReconciliationRun::query()->count())->toBe(1);

    $booking = phase9Booking();
    Payment::create([
        'booking_id' => $booking->id,
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'test_pay_'.Str::uuid(),
        'status' => PaymentStatus::Succeeded,
        'amount' => 10000,
        'currency' => 'PLN',
        'idempotency_key' => 'phase9-admin-reconcile-'.Str::uuid(),
        'paid_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.payment-reconciliation.store'), [
            'provider' => PaymentProvider::Test->value,
            'stale_minutes' => 30,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.payment-reconciliation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payment-reconciliation/index')
            ->has('runs', 2)
            ->has('items', 1)
            ->where('items.0.status', PaymentReconciliationItemStatus::Mismatch->value));
});

test('operator can manage cancellation policies only for manageable organizations', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $foreignOrganization = Organization::factory()->create();
    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Manager,
    ]);

    $this->actingAs($user)
        ->post(route('operator.cancellation-policies.store'), [
            'organization_id' => $organization->id,
            'name' => 'Standard 24h',
            'free_cancellation_hours' => 24,
            'late_refund_percentage' => 0,
            'is_default' => true,
        ])
        ->assertRedirect();

    $policy = CancellationPolicy::query()->firstOrFail();

    expect($policy->organization_id)->toBe($organization->id)
        ->and($policy->is_default)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'cancellation_policy.created')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('operator.cancellation-policies.store'), [
            'organization_id' => $foreignOrganization->id,
            'name' => 'Cudze',
            'free_cancellation_hours' => 24,
            'late_refund_percentage' => 0,
            'is_default' => true,
        ])
        ->assertForbidden();
});

/**
 * @return array{FishingVenue, FishingSpot}
 */
function phase9BookableVenueWithSpot(): array
{
    $venue = FishingVenue::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => 'Lowisko Hardened',
        'slug' => 'lowisko-hardened-'.Str::uuid(),
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
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

function phase9Booking(): Booking
{
    [$venue, $spot] = phase9BookableVenueWithSpot();

    $booking = Booking::create([
        'organization_id' => $venue->organization_id,
        'fishing_venue_id' => $venue->id,
        'status' => BookingStatus::PaymentPending,
        'currency' => 'PLN',
        'subtotal_amount' => 10000,
        'discount_amount' => 0,
        'total_amount' => 10000,
        'commission_bps' => 0,
        'platform_fee_amount' => 0,
        'operator_amount' => 10000,
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-12 10:00:00',
        'ends_at' => '2026-08-12 12:00:00',
        'status' => ReservationStatus::Hold,
        'hold_expires_at' => now()->addMinutes(10),
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

function phase9DataSource(): DataSource
{
    return DataSource::create([
        'provider' => 'phase9',
        'dataset_name' => 'Phase 9 Test',
    ]);
}

/**
 * @return array<string, mixed>
 */
function phase9PublicVenueRecord(string $externalId, string $name): array
{
    return [
        'external_id' => $externalId,
        'name' => $name,
        'description' => 'Opis publiczny.',
        'latitude' => '53.7784000',
        'longitude' => '20.4801000',
        'city' => 'Olsztyn',
        'voivodeship' => 'warminsko-mazurskie',
        'country' => 'PL',
    ];
}
