<?php

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Bookings\Enums\BookingPriceLineType;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Enums\FishingVenueClaimStatus;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('anglers cannot access operator resource screens', function () {
    $angler = User::factory()->create();

    $operatorRoutes = [
        route('operator.dashboard'),
        route('operator.organizations.index'),
        route('operator.bookings.index'),
        route('operator.calendar.index'),
        route('operator.settlements.index'),
        route('operator.cancellation-policies.index'),
        route('operator.fishing-venues.index'),
    ];

    foreach ($operatorRoutes as $operatorRoute) {
        $this->actingAs($angler)
            ->get($operatorRoute)
            ->assertForbidden();
    }
});

test('operator organization listing is scoped to own memberships', function () {
    $operator = User::factory()->venueOwner()->create();
    $foreignOperator = User::factory()->venueOwner()->create();
    $organization = Organization::factory()->create(['name' => 'Wlasne Lowiska']);
    $foreignOrganization = Organization::factory()->create(['name' => 'Cudze Lowiska']);

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);
    OrganizationMember::create([
        'organization_id' => $foreignOrganization->id,
        'user_id' => $foreignOperator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($operator)
        ->get(route('operator.organizations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/organizations/index')
            ->has('organizations', 1)
            ->where('organizations.0.name', $organization->name));
});

test('operator bookings are scoped to manageable organizations', function () {
    $operator = User::factory()->venueOwner()->create();
    $customer = User::factory()->create();
    $organization = Organization::factory()->create();
    $foreignOrganization = Organization::factory()->create();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Manager,
    ]);

    $booking = phase12Booking($organization, $customer);
    $foreignBooking = phase12Booking($foreignOrganization, User::factory()->create());

    $this->actingAs($operator)
        ->get(route('operator.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/bookings/index')
            ->has('bookings', 1)
            ->where('bookings.0.uuid', $booking->uuid));

    $this->actingAs($operator)
        ->get(route('operator.bookings.show', $booking))
        ->assertOk();

    $this->actingAs($operator)
        ->get(route('operator.bookings.show', $foreignBooking))
        ->assertForbidden();
});

test('operators cannot create venues for foreign organizations', function () {
    $operator = User::factory()->venueOwner()->create();
    $organization = Organization::factory()->create();
    $foreignOrganization = Organization::factory()->create();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($operator)
        ->post(route('operator.fishing-venues.store'), [
            'organization_id' => $foreignOrganization->id,
            'name' => 'Nie moje lowisko',
            'venue_type' => FishingVenueType::Private->value,
            'country' => 'PL',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('fishing_venues', [
        'name' => 'Nie moje lowisko',
    ]);
});

test('customer booking screens are limited to the booking owner', function () {
    $customer = User::factory()->create();
    $otherAngler = User::factory()->create();
    $booking = phase12Booking(Organization::factory()->create(), $customer);

    $this->actingAs($otherAngler)
        ->get(route('account.bookings.show', $booking))
        ->assertForbidden();

    $this->actingAs($customer)
        ->get(route('account.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/bookings/show')
            ->where('booking.uuid', $booking->uuid));
});

test('admin resource screens are visible only for admins', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->venueOwner()->create();

    $adminRoutes = [
        route('admin.audit-logs.index'),
        route('admin.payment-reconciliation.index'),
        route('admin.venue-claims.index'),
    ];

    foreach ($adminRoutes as $adminRoute) {
        $this->actingAs($operator)
            ->get($adminRoute)
            ->assertForbidden();

        $this->actingAs($admin)
            ->get($adminRoute)
            ->assertOk();
    }
});

test('phase twelve policies enforce the model access matrix', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->venueOwner()->create();
    $staff = User::factory()->venueOwner()->create();
    $foreignOperator = User::factory()->venueOwner()->create();
    $angler = User::factory()->create();
    $organization = Organization::factory()->create();
    $foreignOrganization = Organization::factory()->create();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);
    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $staff->id,
        'role' => OrganizationMemberRole::Staff,
    ]);
    OrganizationMember::create([
        'organization_id' => $foreignOrganization->id,
        'user_id' => $foreignOperator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $venue = FishingVenue::factory()->create(['organization_id' => $organization->id]);
    $foreignVenue = FishingVenue::factory()->create(['organization_id' => $foreignOrganization->id]);
    $claimableVenue = FishingVenue::factory()->create([
        'organization_id' => null,
        'commercial_status' => FishingVenueCommercialStatus::Claimable,
    ]);
    $spot = FishingSpot::factory()->create(['fishing_venue_id' => $venue->id]);
    $foreignSpot = FishingSpot::factory()->create(['fishing_venue_id' => $foreignVenue->id]);
    $booking = phase12Booking($organization, $angler);
    $foreignBooking = phase12Booking($foreignOrganization, User::factory()->create());
    $payment = $booking->payments()->create([
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'test_pay_'.Str::uuid(),
        'status' => PaymentStatus::Succeeded,
        'amount' => 10000,
        'currency' => 'PLN',
        'idempotency_key' => 'phase12-payment-'.Str::uuid(),
        'paid_at' => now(),
    ]);
    $claim = FishingVenueClaim::create([
        'fishing_venue_id' => $claimableVenue->id,
        'organization_id' => $organization->id,
        'status' => FishingVenueClaimStatus::Pending,
    ]);
    $auditLog = AuditLog::create([
        'actor_user_id' => $admin->id,
        'organization_id' => $organization->id,
        'action' => 'phase12.test',
        'created_at' => now(),
    ]);

    expect(Gate::forUser($angler)->denies('viewAny', Organization::class))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('view', $organization))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('view', $organization))->toBeTrue()
        ->and(Gate::forUser($staff)->denies('manage', $organization))->toBeTrue()
        ->and(Gate::forUser($foreignOperator)->denies('update', $organization))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('view', $venue))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('publish', $venue))->toBeTrue()
        ->and(Gate::forUser($operator)->denies('view', $foreignVenue))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('manage', $spot))->toBeTrue()
        ->and(Gate::forUser($operator)->denies('manage', $foreignSpot))->toBeTrue()
        ->and(Gate::forUser($angler)->allows('viewAsCustomer', $booking))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('viewAsOperator', $booking))->toBeTrue()
        ->and(Gate::forUser($operator)->denies('viewAsOperator', $foreignBooking))->toBeTrue()
        ->and(Gate::forUser($angler)->allows('view', $payment))->toBeTrue()
        ->and(Gate::forUser($foreignOperator)->denies('view', $payment))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('reconcile', Payment::class))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('create', [FishingVenueClaim::class, $claimableVenue, $organization]))->toBeTrue()
        ->and(Gate::forUser($operator)->denies('create', [FishingVenueClaim::class, $claimableVenue, $foreignOrganization]))->toBeTrue()
        ->and(Gate::forUser($operator)->denies('review', $claim))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('review', $claim))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('view', $auditLog))->toBeTrue()
        ->and(Gate::forUser($operator)->denies('view', $auditLog))->toBeTrue();
});

function phase12Booking(Organization $organization, User $customer): Booking
{
    $venue = FishingVenue::factory()->create([
        'organization_id' => $organization->id,
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
    ]);
    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'base_price_amount' => 10000,
        'currency' => 'PLN',
    ]);

    $booking = Booking::create([
        'user_id' => $customer->id,
        'organization_id' => $organization->id,
        'fishing_venue_id' => $venue->id,
        'status' => BookingStatus::Confirmed,
        'currency' => 'PLN',
        'subtotal_amount' => 10000,
        'discount_amount' => 0,
        'total_amount' => 10000,
        'commission_bps' => 0,
        'platform_fee_amount' => 0,
        'operator_amount' => 10000,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'confirmed_at' => now(),
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => Carbon::parse('2026-08-20 10:00:00'),
        'ends_at' => Carbon::parse('2026-08-20 12:00:00'),
        'status' => ReservationStatus::Confirmed,
    ]);

    $booking->priceLines()->create([
        'type' => BookingPriceLineType::Spot,
        'description' => 'Stanowisko testowe',
        'quantity' => 1,
        'unit_amount' => 10000,
        'total_amount' => 10000,
    ]);

    return $booking;
}
