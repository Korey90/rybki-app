<?php

use App\Models\User;
use App\Modules\Fisheries\Enums\FishingSpotStatus;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Actions\ImportGovernmentVenueRecords;
use App\Modules\GovernmentData\Actions\StageGovernmentVenueRecords;
use App\Modules\GovernmentData\Actions\SyncDaneGovPlFishingDistricts;
use App\Modules\GovernmentData\Enums\FishingVenueClaimStatus;
use App\Modules\GovernmentData\Enums\GovernmentDataSyncRunStatus;
use App\Modules\GovernmentData\Enums\GovernmentVenueRecordStatus;
use App\Modules\GovernmentData\Models\DataSource;
use App\Modules\GovernmentData\Models\FishingVenueClaim;
use App\Modules\GovernmentData\Models\FishingVenueSource;
use App\Modules\GovernmentData\Models\GovernmentDataSyncRun;
use App\Modules\GovernmentData\Models\GovernmentVenueRecord;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\Organization;
use App\Modules\Organizations\Models\OrganizationMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('government data import creates an information-only venue with provenance and stays idempotent', function () {
    Carbon::setTestNow('2026-08-11 12:00:00');
    $source = publicDataSource();

    $result = app(ImportGovernmentVenueRecords::class)->handle($source, [
        publicVenueRecord('WP-001', 'Jezioro Testowe'),
    ]);

    expect($result)->toBe([
        'records' => 1,
        'venues_created' => 1,
        'venues_updated' => 0,
    ])
        ->and(FishingVenue::query()->count())->toBe(1)
        ->and(GovernmentVenueRecord::query()->count())->toBe(1)
        ->and(FishingVenueSource::query()->count())->toBe(1);

    $venue = FishingVenue::query()->firstOrFail();
    $record = GovernmentVenueRecord::query()->firstOrFail();

    expect($venue->commercial_status)->toBe(FishingVenueCommercialStatus::InformationOnly)
        ->and($venue->status)->toBe(FishingVenueStatus::Published)
        ->and($venue->venue_type)->toBe(FishingVenueType::WodyPolskie)
        ->and($record->status)->toBe(GovernmentVenueRecordStatus::Imported)
        ->and($record->imported_fishing_venue_id)->toBe($venue->id);

    $result = app(ImportGovernmentVenueRecords::class)->handle($source, [
        [
            ...publicVenueRecord('WP-001', 'Jezioro Testowe'),
            'description' => 'Zaktualizowany opis urzędowy.',
        ],
    ]);

    $venue->refresh();

    expect($result)->toBe([
        'records' => 1,
        'venues_created' => 0,
        'venues_updated' => 1,
    ])
        ->and(FishingVenue::query()->count())->toBe(1)
        ->and(GovernmentVenueRecord::query()->count())->toBe(1)
        ->and(FishingVenueSource::query()->count())->toBe(1)
        ->and($venue->description)->toBe('Zaktualizowany opis urzędowy.');
});

test('government data sync does not overwrite an operator-owned imported venue', function () {
    $source = publicDataSource();
    app(ImportGovernmentVenueRecords::class)->handle($source, [
        publicVenueRecord('WP-002', 'Jezioro Operatora'),
    ]);

    $organization = Organization::factory()->create();
    $venue = FishingVenue::query()->firstOrFail();
    $venue->fill([
        'organization_id' => $organization->id,
        'description' => 'Opis właściciela.',
    ])->save();

    app(ImportGovernmentVenueRecords::class)->handle($source, [
        [
            ...publicVenueRecord('WP-002', 'Jezioro Operatora'),
            'description' => 'Opis z kolejnego importu.',
        ],
    ]);

    $venue->refresh();

    expect($venue->organization_id)->toBe($organization->id)
        ->and($venue->description)->toBe('Opis właściciela.');
});

test('information-only venues are visible in marketplace and API but cannot be booked', function () {
    $source = publicDataSource();
    app(ImportGovernmentVenueRecords::class)->handle($source, [
        publicVenueRecord('WP-003', 'Jezioro Informacyjne'),
    ]);

    $venue = FishingVenue::query()->firstOrFail();

    $this->get(route('marketplace.fishing-venues.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/fishing-venues/index')
            ->has('venues', 1)
            ->where('venues.0.slug', $venue->slug)
            ->where('venues.0.is_bookable', false));

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marketplace/fishing-venues/show')
            ->where('venue.slug', $venue->slug)
            ->where('venue.commercial_status', FishingVenueCommercialStatus::InformationOnly->value)
            ->has('venue.sources', 1)
            ->where('venue.sources.0.external_id', 'WP-003'));

    $this->getJson(route('api.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertJsonPath('venue.slug', $venue->slug)
        ->assertJsonPath('venue.is_bookable', false)
        ->assertJsonCount(1, 'venue.sources');

    $spot = FishingSpot::factory()->create([
        'fishing_venue_id' => $venue->id,
        'status' => FishingSpotStatus::Active,
    ]);

    $this->post(route('checkout.store'), [
        'fishing_spot_uuid' => $spot->uuid,
        'starts_at' => '2026-08-12T10:00:00Z',
        'ends_at' => '2026-08-12T12:00:00Z',
        'customer_name' => 'Jan Kowalski',
        'customer_email' => 'jan@example.com',
        'legal_terms_accepted' => '1',
        'privacy_policy_accepted' => '1',
        'cancellation_rules_accepted' => '1',
    ])->assertNotFound();
});

test('operator can submit a venue claim only for a manageable organization', function () {
    $source = publicDataSource();
    app(ImportGovernmentVenueRecords::class)->handle($source, [
        publicVenueRecord('WP-004', 'Jezioro Claim'),
    ]);

    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $foreignOrganization = Organization::factory()->create();
    $venue = FishingVenue::query()->firstOrFail();

    OrganizationMember::create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => OrganizationMemberRole::Owner,
    ]);

    $this->actingAs($user)
        ->post(route('marketplace.fishing-venues.claims.store', $venue->slug), [
            'organization_id' => $organization->id,
            'evidence' => 'Akt dzierżawy i dane kontaktowe.',
        ])
        ->assertRedirect();

    $claim = FishingVenueClaim::query()->firstOrFail();

    expect($claim->status)->toBe(FishingVenueClaimStatus::Pending)
        ->and($claim->organization_id)->toBe($organization->id)
        ->and($claim->evidence)->toBe('Akt dzierżawy i dane kontaktowe.');

    $this->actingAs($user)
        ->post(route('marketplace.fishing-venues.claims.store', $venue->slug), [
            'organization_id' => $foreignOrganization->id,
            'evidence' => 'Nie moja organizacja.',
        ])
        ->assertForbidden();
});

test('admin can review a venue claim and assign the venue to the organization', function () {
    $source = publicDataSource();
    app(ImportGovernmentVenueRecords::class)->handle($source, [
        publicVenueRecord('WP-005', 'Jezioro Admin'),
    ]);

    $admin = User::factory()->admin()->create();
    $regularUser = User::factory()->create();
    $organization = Organization::factory()->create();
    $venue = FishingVenue::query()->firstOrFail();
    $claim = FishingVenueClaim::create([
        'fishing_venue_id' => $venue->id,
        'organization_id' => $organization->id,
        'status' => FishingVenueClaimStatus::Pending,
        'evidence' => 'Dokumenty operatora.',
    ]);

    $this->actingAs($regularUser)
        ->get(route('admin.venue-claims.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.government-data.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/government-data/index')
            ->has('sources', 1)
            ->has('records', 1));

    $this->actingAs($admin)
        ->get(route('admin.venue-claims.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/venue-claims/index')
            ->has('claims', 1)
            ->where('claims.0.id', $claim->id));

    $this->actingAs($admin)
        ->patch(route('admin.venue-claims.review', $claim), [
            'status' => FishingVenueClaimStatus::Approved->value,
        ])
        ->assertRedirect();

    $venue->refresh();
    $claim->refresh();

    expect($venue->organization_id)->toBe($organization->id)
        ->and($venue->commercial_status)->toBe(FishingVenueCommercialStatus::InformationOnly)
        ->and($claim->status)->toBe(FishingVenueClaimStatus::Approved)
        ->and($claim->reviewed_by)->toBe($admin->id)
        ->and($claim->reviewed_at)->not->toBeNull();
});

test('dane gov pl sync stages latest wody polskie csv resource with raw and normalized payloads', function () {
    Carbon::setTestNow('2026-08-12 12:00:00');

    Http::fake([
        'https://api.dane.gov.pl/1.4/datasets/2914/resources' => Http::response(phase18ResourcesPayload(), 200),
        'https://api.dane.gov.pl/resources/2138521,15-czerwca-2026-r/csv' => Http::response(phase18Csv(), 200, [
            'Content-Type' => 'text/csv',
        ]),
    ]);

    $run = app(SyncDaneGovPlFishingDistricts::class)->handle(limit: 2);

    expect($run->status)->toBe(GovernmentDataSyncRunStatus::Completed)
        ->and($run->resource_external_id)->toBe('2138521')
        ->and($run->records_seen)->toBe(2)
        ->and($run->records_staged)->toBe(2)
        ->and($run->records_needing_review)->toBe(2)
        ->and(DataSource::query()->where('provider', 'wody_polskie')->exists())->toBeTrue()
        ->and(GovernmentDataSyncRun::query()->count())->toBe(1)
        ->and(GovernmentVenueRecord::query()->count())->toBe(2);

    $record = GovernmentVenueRecord::query()
        ->where('name', 'Obwód rybacki Kanału Augustowskiego Nr 1')
        ->firstOrFail();

    expect($record->status)->toBe(GovernmentVenueRecordStatus::NeedsReview)
        ->and($record->source_payload['row']['RZGW'])->toBe('RZGW w Białymstoku')
        ->and($record->normalized_payload['venue_type'])->toBe(FishingVenueType::WodyPolskie->value)
        ->and($record->quality_flags)->toContain('missing_coordinates', 'missing_city')
        ->and($record->quality_score)->toBeLessThan(100);

    Http::assertSentCount(2);
});

test('admin can manually match staged public record without overwriting operator venue', function () {
    Carbon::setTestNow('2026-08-12 12:00:00');

    $admin = User::factory()->admin()->create();
    $organization = Organization::factory()->create();
    $venue = FishingVenue::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Obwód rybacki Kanału Augustowskiego Nr 1',
        'description' => 'Opis operatora zostaje.',
        'commercial_status' => FishingVenueCommercialStatus::Bookable,
        'status' => FishingVenueStatus::Published,
        'published_at' => now(),
    ]);
    $source = phase18RealDataSource();

    app(StageGovernmentVenueRecords::class)->handle($source, [
        phase18NormalizedPublicRecord('WP-REAL-001', 'Obwód rybacki Kanału Augustowskiego Nr 1'),
    ]);

    $record = GovernmentVenueRecord::query()->firstOrFail();

    expect($record->status)->toBe(GovernmentVenueRecordStatus::NeedsReview)
        ->and($record->match_confidence)->toBeGreaterThan(50)
        ->and($record->match_candidates[0]['venue_uuid'])->toBe($venue->uuid);

    $this->actingAs($admin)
        ->patch(route('admin.government-data.records.match', $record), [
            'fishing_venue_uuid' => $venue->uuid,
        ])
        ->assertRedirect();

    $record->refresh();
    $venue->refresh();

    expect($record->status)->toBe(GovernmentVenueRecordStatus::Matched)
        ->and($record->matched_fishing_venue_id)->toBe($venue->id)
        ->and($venue->description)->toBe('Opis operatora zostaje.')
        ->and(FishingVenueSource::query()->where('fishing_venue_id', $venue->id)->count())->toBe(1);

    $this->get(route('marketplace.fishing-venues.show', $venue->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('venue.slug', $venue->slug)
            ->has('venue.sources', 1)
            ->where('venue.sources.0.external_id', 'WP-REAL-001'));
});

test('admin can import staged public record as information profile and skip another record', function () {
    Carbon::setTestNow('2026-08-12 12:00:00');

    $admin = User::factory()->admin()->create();
    $source = phase18RealDataSource();

    app(StageGovernmentVenueRecords::class)->handle($source, [
        phase18NormalizedPublicRecord('WP-REAL-002', 'Obwód rybacki Jeziora Import'),
        phase18NormalizedPublicRecord('WP-REAL-003', 'Obwód rybacki Jeziora Skip'),
    ]);

    $importRecord = GovernmentVenueRecord::query()->where('external_id', 'WP-REAL-002')->firstOrFail();
    $skipRecord = GovernmentVenueRecord::query()->where('external_id', 'WP-REAL-003')->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.government-data.records.import', $importRecord))
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('admin.government-data.records.skip', $skipRecord))
        ->assertRedirect();

    $importRecord->refresh();
    $skipRecord->refresh();

    $venue = $importRecord->importedVenue()->firstOrFail();

    expect($importRecord->status)->toBe(GovernmentVenueRecordStatus::Imported)
        ->and($venue->commercial_status)->toBe(FishingVenueCommercialStatus::InformationOnly)
        ->and($venue->status)->toBe(FishingVenueStatus::Published)
        ->and($skipRecord->status)->toBe(GovernmentVenueRecordStatus::Skipped)
        ->and(FishingVenueSource::query()->where('external_id', 'WP-REAL-002')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('admin.government-data.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/government-data/index')
            ->where('status_counts.IMPORTED', 1)
            ->where('status_counts.SKIPPED', 1)
            ->has('records', 2)
            ->has('candidate_venues'));
});

function publicDataSource(): DataSource
{
    return DataSource::create([
        'provider' => 'wody_polskie',
        'dataset_name' => 'Ujednolicony wykaz obwodów rybackich',
        'dataset_url' => 'https://example.test/dataset.csv',
    ]);
}

function phase18RealDataSource(): DataSource
{
    return DataSource::create([
        'provider' => 'wody_polskie',
        'dataset_name' => 'Ujednolicony wykaz obwodów rybackich',
        'dataset_url' => 'https://dane.gov.pl/pl/dataset/2914,ujednolicony-wykaz-obwodow-rybackich',
        'external_dataset_id' => '2914',
        'default_format' => 'csv',
        'sync_enabled' => true,
    ]);
}

/**
 * @return array<string, mixed>
 */
function phase18NormalizedPublicRecord(string $externalId, string $name): array
{
    return [
        'external_id' => $externalId,
        'name' => $name,
        'description' => 'Opis z realnego źródła publicznego.',
        'venue_type' => FishingVenueType::WodyPolskie->value,
        'city' => 'Olsztyn',
        'voivodeship' => 'warmińsko-mazurskie',
        'country' => 'PL',
        'source_updated_at' => '2026-06-15T00:00:00Z',
        '_source_payload' => [
            'provider' => 'wody_polskie',
            'row' => [
                'Nazwa obwodu rybackiego' => $name,
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function phase18ResourcesPayload(): array
{
    return [
        'data' => [
            [
                'type' => 'resource',
                'id' => '938872',
                'attributes' => [
                    'title' => '15 grudnia 2025 r.',
                    'format' => 'xlsx',
                    'data_date' => '2025-12-15',
                    'csv_download_url' => 'https://api.dane.gov.pl/resources/938872,15-grudnia-2025-r/csv',
                ],
            ],
            [
                'type' => 'resource',
                'id' => '2138521',
                'attributes' => [
                    'title' => '15 czerwca 2026 r.',
                    'format' => 'xlsx',
                    'data_date' => '2026-06-15',
                    'csv_download_url' => 'https://api.dane.gov.pl/resources/2138521,15-czerwca-2026-r/csv',
                ],
            ],
        ],
    ];
}

function phase18Csv(): string
{
    return implode("\n", [
        'Lp,RZGW,Nazwa obwodu rybackiego,Opis obwodu,Termin rozpoczęcia umowy,Termin wygaśnięcia umowy',
        '1,RZGW w Białymstoku,Kanału Augustowskiego Nr 1,"Obwód rybacki obejmuje wody:',
        'a) Kanału Augustowskiego na odcinku testowym",2024-01-01,2034-01-01',
        '2,RZGW w Gdańsku,Jeziora Testowego Nr 2,"Opis drugiego obwodu",2025-02-01,2035-02-01',
    ]);
}

/**
 * @return array<string, mixed>
 */
function publicVenueRecord(string $externalId, string $name): array
{
    return [
        'external_id' => $externalId,
        'name' => $name,
        'description' => 'Opis z publicznego źródła.',
        'venue_type' => FishingVenueType::WodyPolskie->value,
        'latitude' => '53.7784000',
        'longitude' => '20.4801000',
        'city' => 'Olsztyn',
        'voivodeship' => 'warminsko-mazurskie',
        'country' => 'PL',
        'source_updated_at' => '2026-06-15T00:00:00Z',
    ];
}
