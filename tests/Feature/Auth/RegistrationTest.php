<?php

use App\Enums\UserAccountType;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'account_type' => UserAccountType::Angler->value,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'account_type' => UserAccountType::Angler->value,
    ]);
});

test('venue owners can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Operator User',
        'email' => 'operator@example.com',
        'account_type' => UserAccountType::VenueOwner->value,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('users', [
        'email' => 'operator@example.com',
        'account_type' => UserAccountType::VenueOwner->value,
    ]);
});

test('account type is required during registration', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'missing-type@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('account_type');
    $this->assertGuest();
});
