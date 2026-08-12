<?php

use App\Models\User;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Enums\ProviderAccountOnboardingStatus;
use App\Modules\Payments\Enums\RefundStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\ProviderAccount;
use App\Modules\Settlements\Actions\GenerateSettlementPeriod;
use App\Modules\Settlements\Enums\OperatorPayoutStatus;
use App\Modules\Settlements\Enums\SettlementItemType;
use App\Modules\Settlements\Enums\SettlementPeriodStatus;
use App\Modules\Settlements\Models\SettlementPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('admin configures platform commission and new booking holds snapshot it', function () {
    Carbon::setTestNow('2026-08-12 09:00:00');

    $admin = User::factory()->admin()->venueOwner()->create();
    [, $spot] = phase17BookableSpot();

    $this->actingAs($admin)
        ->patch(route('admin.settlements.commission.update'), [
            'default_commission_bps' => 1250,
        ])
        ->assertRedirect();

    $this->postJson(route('api.booking-holds.store'), phase17BookingHoldPayload($spot))
        ->assertCreated();

    $booking = Booking::query()->firstOrFail();

    expect($booking->commission_bps)->toBe(1250)
        ->and($booking->platform_fee_amount)->toBe(1250)
        ->and($booking->operator_amount)->toBe(8750);
});

test('operator generates settlement period with refunds and exports csv and pdf', function () {
    Carbon::setTestNow('2026-08-12 10:00:00');

    [$operator, $organization, $booking, $payment] = phase17SettlementScenario();

    $this->actingAs($operator)
        ->post(route('operator.settlements.store'), [
            'organization_uuid' => $organization->uuid,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ])
        ->assertRedirect();

    $settlementPeriod = SettlementPeriod::query()
        ->with(['items', 'payout'])
        ->where('organization_id', $organization->id)
        ->firstOrFail();

    expect($settlementPeriod->gross_amount)->toBe(10000)
        ->and($settlementPeriod->platform_fee_amount)->toBe(1000)
        ->and($settlementPeriod->refund_amount)->toBe(3000)
        ->and($settlementPeriod->net_payout_amount)->toBe(6000)
        ->and($settlementPeriod->bookings_count)->toBe(1)
        ->and($settlementPeriod->refunds_count)->toBe(1)
        ->and($settlementPeriod->payout?->amount)->toBe(6000)
        ->and($settlementPeriod->items)->toHaveCount(2)
        ->and($settlementPeriod->items->pluck('type')->map(fn (SettlementItemType $type): string => $type->value)->all())
        ->toContain(SettlementItemType::Payment->value, SettlementItemType::Refund->value);

    $csv = $this->actingAs($operator)
        ->get(route('operator.settlements.export', ['settlementPeriod' => $settlementPeriod, 'format' => 'csv']));

    $csv->assertOk();
    expect($csv->streamedContent())->toContain($booking->booking_number, $payment->provider_payment_id, 'test_refund_phase17');

    $pdf = $this->actingAs($operator)
        ->get(route('operator.settlements.export', ['settlementPeriod' => $settlementPeriod, 'format' => 'pdf']));

    $pdf->assertOk();
    expect($pdf->getContent())->toStartWith('%PDF-1.4');
});

test('admin sees all settlements and can mark payout as paid', function () {
    Carbon::setTestNow('2026-08-12 10:00:00');

    [, $organization] = phase17SettlementScenario();
    $admin = User::factory()->admin()->venueOwner()->create();

    $this->actingAs($admin)
        ->post(route('admin.settlements.store'), [
            'organization_uuid' => $organization->uuid,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ])
        ->assertRedirect();

    $settlementPeriod = SettlementPeriod::query()->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.settlements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settlements/index')
            ->where('settlement_periods.0.uuid', $settlementPeriod->uuid)
            ->where('settlement_periods.0.organization.name', $organization->name)
            ->where('settlement_periods.0.net_payout_amount', 6000));

    $this->actingAs($admin)
        ->patch(route('admin.settlements.payout.update', $settlementPeriod), [
            'status' => OperatorPayoutStatus::Paid->value,
            'provider_payout_id' => 'po_phase17_001',
        ])
        ->assertRedirect();

    $settlementPeriod->refresh()->load('payout');

    expect($settlementPeriod->status)->toBe(SettlementPeriodStatus::Paid)
        ->and($settlementPeriod->provider_payout_id)->toBe('po_phase17_001')
        ->and($settlementPeriod->paid_at)->not->toBeNull()
        ->and($settlementPeriod->payout?->status)->toBe(OperatorPayoutStatus::Paid)
        ->and($settlementPeriod->payout?->provider_payout_id)->toBe('po_phase17_001');
});

test('operator cannot generate or export settlement for foreign organization', function () {
    Carbon::setTestNow('2026-08-12 10:00:00');

    [$operator] = phase17SettlementScenario();
    [, $foreignOrganization] = phase17SettlementScenario();
    $foreignSettlement = app(GenerateSettlementPeriod::class)->handle(
        $foreignOrganization,
        '2026-08-01',
        '2026-08-31',
    );

    $this->actingAs($operator)
        ->post(route('operator.settlements.store'), [
            'organization_uuid' => $foreignOrganization->uuid,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
        ])
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('operator.settlements.export', ['settlementPeriod' => $foreignSettlement, 'format' => 'csv']))
        ->assertForbidden();
});

/**
 * @return array{User, Organization, Booking, Payment}
 */
function phase17SettlementScenario(): array
{
    $operator = User::factory()->venueOwner()->create();
    $organization = Organization::factory()->create();
    $organization->members()->create([
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    ProviderAccount::query()->create([
        'organization_id' => $organization->id,
        'provider' => PaymentProvider::Test,
        'external_account_id' => 'acct_phase17_'.Str::lower(Str::random(6)),
        'onboarding_status' => ProviderAccountOnboardingStatus::Enabled,
        'payments_enabled' => true,
        'payouts_enabled' => true,
        'last_synced_at' => now(),
    ]);

    $venue = FishingVenue::factory()->create([
        'organization_id' => $organization->id,
    ]);
    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'base_price_amount' => 10000,
    ]);

    Carbon::setTestNow('2026-08-05 12:00:00');
    [$booking, $payment] = phase17CreatePaidBooking($organization, $venue, $spot, 10000);

    Carbon::setTestNow('2026-08-20 12:00:00');
    $payment->refunds()->create([
        'booking_id' => $booking->id,
        'provider_refund_id' => 'test_refund_phase17',
        'amount' => 3000,
        'currency' => 'PLN',
        'reason' => 'Częściowy zwrot po anulacji',
        'status' => RefundStatus::Succeeded,
        'initiated_by_user_id' => $operator->id,
    ]);

    Carbon::setTestNow('2026-08-12 10:00:00');

    return [$operator, $organization, $booking, $payment];
}

/**
 * @return array{FishingVenue, FishingSpot}
 */
function phase17BookableSpot(): array
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
 * @return array<string, string>
 */
function phase17BookingHoldPayload(FishingSpot $spot): array
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
 * @return array{Booking, Payment}
 */
function phase17CreatePaidBooking(
    Organization $organization,
    FishingVenue $venue,
    FishingSpot $spot,
    int $totalAmount
): array {
    $platformFeeAmount = intdiv($totalAmount * 1000, 10000);

    $booking = Booking::query()->create([
        'organization_id' => $organization->id,
        'fishing_venue_id' => $venue->id,
        'status' => BookingStatus::PartiallyRefunded,
        'currency' => 'PLN',
        'subtotal_amount' => $totalAmount,
        'discount_amount' => 0,
        'total_amount' => $totalAmount,
        'commission_bps' => 1000,
        'platform_fee_amount' => $platformFeeAmount,
        'operator_amount' => $totalAmount - $platformFeeAmount,
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
        'customer_phone' => '+48123123123',
        'confirmed_at' => now(),
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-16 08:00:00',
        'ends_at' => '2026-08-16 18:00:00',
        'status' => ReservationStatus::Confirmed,
    ]);

    $booking->priceLines()->create([
        'type' => BookingPriceLineType::Spot,
        'description' => $spot->name.' x 1 dni',
        'quantity' => 1,
        'unit_amount' => $totalAmount,
        'total_amount' => $totalAmount,
    ]);

    $payment = $booking->payments()->create([
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'test_payment_phase17_'.Str::lower(Str::random(8)),
        'status' => PaymentStatus::PartiallyRefunded,
        'amount' => $totalAmount,
        'currency' => 'PLN',
        'idempotency_key' => 'phase17-'.Str::uuid(),
        'paid_at' => now(),
    ]);

    return [$booking, $payment];
}
