<?php

use App\Enums\UserAccountType;
use App\Models\User;
use App\Modules\Legal\Enums\LegalDocumentType;
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
        'legal_terms_accepted' => '1',
        'privacy_policy_accepted' => '1',
        'marketing_consent' => '1',
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'account_type' => UserAccountType::Angler->value,
    ]);
    $this->assertDatabaseHas('legal_acceptances', [
        'user_id' => $user->id,
        'document_type' => LegalDocumentType::Terms->value,
        'document_version' => '2026.08.12-mvp',
        'context' => 'registration',
    ]);
    $this->assertDatabaseHas('legal_acceptances', [
        'user_id' => $user->id,
        'document_type' => LegalDocumentType::PrivacyPolicy->value,
        'document_version' => '2026.08.12-mvp',
        'context' => 'registration',
        'marketing_consent' => true,
    ]);

    expect($user->marketing_consent_at)->not->toBeNull()
        ->and($user->marketing_consent_document_version)->toBe('2026.08.12-mvp');
});

test('venue owners can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Operator User',
        'email' => 'operator@example.com',
        'account_type' => UserAccountType::VenueOwner->value,
        'password' => 'password',
        'password_confirmation' => 'password',
        'legal_terms_accepted' => '1',
        'privacy_policy_accepted' => '1',
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
        'legal_terms_accepted' => '1',
        'privacy_policy_accepted' => '1',
    ]);

    $response->assertSessionHasErrors('account_type');
    $this->assertGuest();
});

test('legal documents must be accepted during registration', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'missing-legal@example.com',
        'account_type' => UserAccountType::Angler->value,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors([
        'legal_terms_accepted',
        'privacy_policy_accepted',
    ]);
    $this->assertGuest();
});
