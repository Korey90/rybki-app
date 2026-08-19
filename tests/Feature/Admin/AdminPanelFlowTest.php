<?php

use App\Models\User;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueMediaType;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('admin platform lists are searchable and protected', function () {
    Carbon::setTestNow('2026-08-12 12:00:00');

    $regularUser = User::factory()->create();
    $admin = User::factory()->admin()->create();
    [$operator, $organization, $venue, , $booking, $payment] = phase20AdminFixture();

    $this->actingAs($regularUser)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => $operator->email]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users', 1)
            ->where('users.0.email', $operator->email));

    $this->actingAs($admin)
        ->get(route('admin.operators.index', ['q' => $organization->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/operators/index')
            ->has('operators', 1)
            ->where('operators.0.email', $operator->email));

    $this->actingAs($admin)
        ->get(route('admin.organizations.index', ['q' => $organization->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organizations/index')
            ->has('organizations', 1)
            ->where('organizations.0.uuid', $organization->uuid));

    $this->actingAs($admin)
        ->get(route('admin.fishing-venues.index', ['q' => $venue->name]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/fishing-venues/index')
            ->has('venues', 1)
            ->where('venues.0.uuid', $venue->uuid));

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['q' => $booking->booking_number]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/bookings/index')
            ->has('bookings', 1)
            ->where('bookings.0.booking_number', $booking->booking_number));

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['q' => $payment->provider_payment_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/payments/index')
            ->has('payments', 1)
            ->where('payments.0.uuid', $payment->uuid));
});

test('admin can export platform data as csv', function () {
    $admin = User::factory()->admin()->create();
    phase20AdminFixture();

    $this->actingAs($admin)
        ->get(route('admin.users.export'))
        ->assertOk()
        ->assertDownload('admin-users.csv');

    $this->actingAs($admin)
        ->get(route('admin.bookings.export'))
        ->assertOk()
        ->assertDownload('admin-bookings.csv');

    $this->actingAs($admin)
        ->get(route('admin.payments.export'))
        ->assertOk()
        ->assertDownload('admin-payments.csv');
});

test('admin can suspend a venue and hide venue media from the public profile', function () {
    Carbon::setTestNow('2026-08-12 12:00:00');

    $admin = User::factory()->admin()->create();
    $fixture = phase20AdminFixture();
    $venue = $fixture[2];
    $media = $venue->media()->firstOrFail();

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('venue.media', 1));

    $this->actingAs($admin)
        ->patch(route('admin.fishing-venues.media.moderate', $media), [
            'hidden' => '1',
            'hidden_reason' => 'Zdjęcie narusza zasady.',
        ])
        ->assertRedirect();

    expect($media->refresh()->hidden_at)->not->toBeNull()
        ->and($media->hidden_reason)->toBe('Zdjęcie narusza zasady.')
        ->and($media->hidden_by_user_id)->toBe($admin->id);

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('venue.media', 0)
            ->where('venue.media_count', 0));

    $this->actingAs($admin)
        ->patch(route('admin.fishing-venues.moderate', $venue), [
            'status' => FishingVenueStatus::Suspended->value,
            'moderation_reason' => 'Weryfikacja nadużycia.',
        ])
        ->assertRedirect();

    expect($venue->refresh()->status)->toBe(FishingVenueStatus::Suspended)
        ->and($venue->moderation_reason)->toBe('Weryfikacja nadużycia.')
        ->and($venue->moderated_by_user_id)->toBe($admin->id);

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertNotFound();

    $this->actingAs($admin)
        ->get(route('admin.audit-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit-logs/index')
            ->has('logs', 2));
});

/**
 * @return array{User, Organization, FishingVenue, FishingSpot, Booking, Payment}
 */
function phase20AdminFixture(): array
{
    $operator = User::factory()->venueOwner()->create([
        'name' => 'Operator Admin Flow',
        'email' => 'operator-admin-flow@example.com',
    ]);
    $customer = User::factory()->create([
        'name' => 'Klient Admin Flow',
        'email' => 'klient-admin-flow@example.com',
    ]);
    $organization = Organization::factory()->create([
        'name' => 'Admin Flow Organizacja',
        'tax_id' => '5250000000',
    ]);

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $operator->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $venue = FishingVenue::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Admin Flow Lowisko',
        'slug' => 'admin-flow-lowisko',
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
    ]);

    FishingVenueMedia::create([
        'fishing_venue_id' => $venue->id,
        'storage_path' => 'venues/admin-flow.jpg',
        'type' => FishingVenueMediaType::Image,
        'sort_order' => 1,
    ]);

    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'name' => 'Stanowisko Admin',
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
        'commission_bps' => 1000,
        'platform_fee_amount' => 1000,
        'operator_amount' => 9000,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'confirmed_at' => now(),
    ]);

    $booking->spotReservations()->create([
        'fishing_spot_id' => $spot->id,
        'starts_at' => '2026-08-12 10:00:00',
        'ends_at' => '2026-08-12 12:00:00',
        'status' => ReservationStatus::Confirmed,
    ]);

    $payment = $booking->payments()->create([
        'provider' => PaymentProvider::Test,
        'provider_payment_id' => 'admin-flow-payment-'.Str::lower(Str::random(6)),
        'status' => PaymentStatus::Succeeded,
        'amount' => 10000,
        'currency' => 'PLN',
        'idempotency_key' => 'admin-flow-'.Str::uuid(),
        'paid_at' => now(),
    ]);

    return [$operator, $organization, $venue, $spot, $booking, $payment];
}
