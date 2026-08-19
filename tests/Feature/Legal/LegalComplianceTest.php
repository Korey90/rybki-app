<?php

use App\Models\User;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Legal\Actions\RecordLegalAcceptance;
use App\Modules\Legal\Enums\LegalDocumentType;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('public legal documents are accessible with active versions', function () {
    $documents = [
        'legal.terms' => ['slug' => 'regulamin', 'type' => LegalDocumentType::Terms->value],
        'legal.privacy' => ['slug' => 'polityka-prywatnosci', 'type' => LegalDocumentType::PrivacyPolicy->value],
        'legal.cancellation' => ['slug' => 'zasady-anulowania', 'type' => LegalDocumentType::CancellationRules->value],
        'legal.contact' => ['slug' => 'kontakt', 'type' => LegalDocumentType::Contact->value],
        'legal.operators' => ['slug' => 'dla-operatorow', 'type' => LegalDocumentType::OperatorInfo->value],
    ];

    foreach ($documents as $routeName => $expected) {
        $this->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal/show')
                ->where('document.slug', $expected['slug'])
                ->where('document.type', $expected['type'])
                ->where('document.version', '2026.08.12-mvp'));
    }
});

test('checkout requires legal acceptances before creating a booking', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    [, $spot] = phase23VenueWithSpot('Lowisko Bez Zgod');

    $this->post(route('checkout.store'), [
        'fishing_spot_uuid' => $spot->uuid,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
    ])->assertSessionHasErrors([
        'legal_terms_accepted',
        'privacy_policy_accepted',
        'cancellation_rules_accepted',
    ]);

    expect(Booking::query()->count())->toBe(0);
});

test('guest checkout stores accepted legal document versions on booking', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    [, $spot] = phase23VenueWithSpot('Lowisko Zgody');

    $response = $this->post(route('checkout.store'), [
        'fishing_spot_uuid' => $spot->uuid,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
        'legal_terms_accepted' => '1',
        'privacy_policy_accepted' => '1',
        'cancellation_rules_accepted' => '1',
    ]);

    $booking = Booking::query()->with('legalAcceptances')->firstOrFail();

    $response->assertRedirect(route('checkout.show', $booking));

    expect($booking->status)->toBe(BookingStatus::PaymentPending)
        ->and($booking->legalAcceptances)->toHaveCount(3);

    foreach ([
        LegalDocumentType::Terms,
        LegalDocumentType::PrivacyPolicy,
        LegalDocumentType::CancellationRules,
    ] as $documentType) {
        $this->assertDatabaseHas('legal_acceptances', [
            'user_id' => null,
            'booking_id' => $booking->id,
            'customer_email' => 'jan@example.com',
            'document_type' => $documentType->value,
            'document_version' => '2026.08.12-mvp',
            'context' => 'checkout',
        ]);
    }
});

test('user can export account and legal acceptance data', function () {
    $user = User::factory()->create([
        'email' => 'export@example.com',
    ]);

    app(RecordLegalAcceptance::class)->handle(
        LegalDocumentType::Terms,
        'registration',
        user: $user,
    );

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('profile.export'));

    $response->assertOk();
    $response->assertDownload('rybki-data-export.json');

    $payload = json_decode($response->streamedContent(), true);

    expect($payload['user']['email'])->toBe('export@example.com')
        ->and($payload['legal_acceptances'])->toHaveCount(1)
        ->and($payload['legal_acceptances'][0]['document_type'])->toBe(LegalDocumentType::Terms->value)
        ->and($payload['legal_acceptances'][0]['document_version'])->toBe('2026.08.12-mvp');
});

/**
 * @return array{FishingVenue, FishingSpot}
 */
function phase23VenueWithSpot(string $name): array
{
    $venue = FishingVenue::factory()->create([
        'organization_id' => Organization::factory(),
        'name' => $name,
        'slug' => Str::slug($name),
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
        'city' => 'Olsztyn',
        'voivodeship' => 'warminsko-mazurskie',
        'latitude' => '53.7784',
        'longitude' => '20.4801',
    ]);

    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'name' => 'Stanowisko 1',
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
