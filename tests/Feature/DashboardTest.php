<?php

use App\Enums\UserAccountType;
use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('anglers are redirected from dashboard to marketplace', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('marketplace.fishing-venues.index'));
});

test('venue owners without organization are redirected to operator onboarding', function () {
    $user = User::factory()->venueOwner()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('operator.organizations.index'));
});

test('venue owners with organization are redirected to operator dashboard', function () {
    $user = User::factory()->venueOwner()->create();
    $organization = Organization::factory()->create();

    $organization->members()->create([
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('operator.dashboard'));
});

test('existing organization owners are redirected to operator dashboard', function () {
    $user = User::factory()->create([
        'account_type' => UserAccountType::Angler,
    ]);
    $organization = Organization::factory()->create();

    $organization->members()->create([
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Manager,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('operator.dashboard'));
});
