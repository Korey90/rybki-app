<?php

use App\Enums\UserAccountType;
use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the operator organizations page', function () {
    $response = $this->get(route('operator.organizations.index'));

    $response->assertRedirect(route('login'));
});

test('venue owners can visit the operator organizations page', function () {
    $user = User::factory()->venueOwner()->create();

    $this->actingAs($user);

    $response = $this->get(route('operator.organizations.index'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/organizations/index')
            ->has('organizations', 0));
});

test('anglers cannot visit the operator organizations page directly', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('operator.organizations.index'))
        ->assertForbidden();
});

test('venue owners can create an owner organization', function () {
    $user = User::factory()->venueOwner()->create();

    $this->actingAs($user);

    $response = $this->post(route('operator.organizations.store'), [
        'name' => 'Lowisko Mazury',
        'legal_name' => 'Lowisko Mazury Sp. z o.o.',
        'tax_id' => '1234567890',
        'regon' => '123456789',
        'country' => 'pl',
        'address' => 'Ul. Jeziorna 1, 11-100 Mazury',
    ]);

    $response->assertRedirect(route('operator.organizations.index'));

    $organization = Organization::query()->firstOrFail();

    expect($organization->name)->toBe('Lowisko Mazury')
        ->and($organization->country)->toBe('PL');

    $this->assertDatabaseHas('organization_members', [
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner->value,
    ]);

    expect($user->fresh()->account_type)->toBe(UserAccountType::VenueOwner);
});
