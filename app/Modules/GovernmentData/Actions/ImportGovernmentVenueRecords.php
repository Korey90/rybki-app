<?php

namespace App\Modules\GovernmentData\Actions;

use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Enums\GovernmentVenueRecordStatus;
use App\Modules\GovernmentData\Models\DataSource;
use App\Modules\GovernmentData\Models\FishingVenueSource;
use App\Modules\GovernmentData\Models\GovernmentVenueRecord;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImportGovernmentVenueRecords
{
    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{records:int, venues_created:int, venues_updated:int}
     */
    public function handle(DataSource $dataSource, array $records): array
    {
        $result = [
            'records' => count($records),
            'venues_created' => 0,
            'venues_updated' => 0,
        ];

        foreach ($records as $record) {
            $normalized = $this->normalizeRecord($record);
            $normalizedPayload = $this->normalizedPayload($normalized);
            [$qualityFlags, $qualityScore] = $this->quality($normalizedPayload);

            DB::transaction(function () use ($dataSource, $normalized, $normalizedPayload, $qualityFlags, $qualityScore, $record, &$result): void {
                $governmentRecord = GovernmentVenueRecord::query()->firstOrNew([
                    'data_source_id' => $dataSource->id,
                    'external_id' => $normalized['external_id'],
                ]);

                $venue = $this->findExistingVenue($dataSource, $normalized, $governmentRecord);

                if (! $venue instanceof FishingVenue) {
                    $venue = $this->createVenue($normalized);
                    $result['venues_created']++;
                } elseif ($this->canUpdateImportedVenue($venue)) {
                    $this->updateVenue($venue, $normalized);
                    $result['venues_updated']++;
                }

                $source = FishingVenueSource::query()->updateOrCreate(
                    [
                        'data_source_id' => $dataSource->id,
                        'external_id' => $normalized['external_id'],
                    ],
                    [
                        'fishing_venue_id' => $venue->id,
                        'source_payload' => $record,
                        'source_updated_at' => $normalized['source_updated_at'],
                        'last_synced_at' => now(),
                    ],
                );

                $governmentRecord->fill([
                    'name' => $normalized['name'],
                    'description' => $normalized['description'],
                    'venue_type' => $normalized['venue_type'],
                    'latitude' => $normalized['latitude'],
                    'longitude' => $normalized['longitude'],
                    'address' => $normalized['address'],
                    'city' => $normalized['city'],
                    'postal_code' => $normalized['postal_code'],
                    'voivodeship' => $normalized['voivodeship'],
                    'country' => $normalized['country'],
                    'source_payload' => $record,
                    'normalized_payload' => $normalizedPayload,
                    'quality_flags' => $qualityFlags,
                    'quality_score' => $qualityScore,
                    'source_updated_at' => $normalized['source_updated_at'],
                    'status' => GovernmentVenueRecordStatus::Imported,
                    'matched_fishing_venue_id' => $source->fishing_venue_id,
                    'imported_fishing_venue_id' => $source->fishing_venue_id,
                ])->save();
            });
        }

        $dataSource->forceFill(['last_successful_sync_at' => now()])->save();

        $this->recordAuditLog->handle(
            action: 'government_data.import_completed',
            subject: $dataSource,
            after: [
                'provider' => $dataSource->provider,
                'dataset_name' => $dataSource->dataset_name,
                'records' => $result['records'],
                'venues_created' => $result['venues_created'],
                'venues_updated' => $result['venues_updated'],
            ],
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function findExistingVenue(
        DataSource $dataSource,
        array $normalized,
        GovernmentVenueRecord $governmentRecord
    ): ?FishingVenue {
        $source = FishingVenueSource::query()
            ->with('venue')
            ->where('data_source_id', $dataSource->id)
            ->where('external_id', $normalized['external_id'])
            ->first();

        if ($source instanceof FishingVenueSource && $source->venue instanceof FishingVenue) {
            return $source->venue;
        }

        if ($governmentRecord->exists) {
            $venue = $governmentRecord->importedVenue()->first();

            if ($venue instanceof FishingVenue) {
                return $venue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function createVenue(array $normalized): FishingVenue
    {
        return FishingVenue::create([
            'organization_id' => null,
            'name' => $normalized['name'],
            'slug' => $this->uniqueSlug($normalized['name']),
            'description' => $normalized['description'],
            'venue_type' => $normalized['venue_type'],
            'commercial_status' => FishingVenueCommercialStatus::InformationOnly,
            'latitude' => $normalized['latitude'],
            'longitude' => $normalized['longitude'],
            'address' => $normalized['address'],
            'city' => $normalized['city'],
            'postal_code' => $normalized['postal_code'],
            'voivodeship' => $normalized['voivodeship'],
            'country' => $normalized['country'],
            'status' => FishingVenueStatus::Published,
            'published_at' => now(),
        ]);
    }

    private function canUpdateImportedVenue(FishingVenue $venue): bool
    {
        return $venue->organization_id === null
            && $venue->commercial_status === FishingVenueCommercialStatus::InformationOnly;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function updateVenue(FishingVenue $venue, array $normalized): void
    {
        $venue->fill([
            'name' => $normalized['name'],
            'description' => $normalized['description'],
            'venue_type' => $normalized['venue_type'],
            'latitude' => $normalized['latitude'],
            'longitude' => $normalized['longitude'],
            'address' => $normalized['address'],
            'city' => $normalized['city'],
            'postal_code' => $normalized['postal_code'],
            'voivodeship' => $normalized['voivodeship'],
            'country' => $normalized['country'],
            'status' => FishingVenueStatus::Published,
            'published_at' => $venue->published_at ?? now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{
     *     external_id:string,
     *     name:string,
     *     description:?string,
     *     venue_type:FishingVenueType,
     *     latitude:?string,
     *     longitude:?string,
     *     address:?string,
     *     city:?string,
     *     postal_code:?string,
     *     voivodeship:?string,
     *     country:string,
     *     source_updated_at:?CarbonInterface
     * }
     */
    private function normalizeRecord(array $record): array
    {
        $externalId = $this->nullableString($record['external_id'] ?? null);
        $name = $this->nullableString($record['name'] ?? null);

        if ($externalId === null || $name === null) {
            throw new InvalidArgumentException('Government venue records require external_id and name.');
        }

        return [
            'external_id' => $externalId,
            'name' => $name,
            'description' => $this->nullableString($record['description'] ?? null),
            'venue_type' => $this->venueType($record['venue_type'] ?? null),
            'latitude' => $this->nullableDecimalString($record['latitude'] ?? null),
            'longitude' => $this->nullableDecimalString($record['longitude'] ?? null),
            'address' => $this->nullableString($record['address'] ?? null),
            'city' => $this->nullableString($record['city'] ?? null),
            'postal_code' => $this->nullableString($record['postal_code'] ?? null),
            'voivodeship' => $this->nullableString($record['voivodeship'] ?? null),
            'country' => Str::upper($this->nullableString($record['country'] ?? null) ?? 'PL'),
            'source_updated_at' => $this->nullableDate($record['source_updated_at'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDecimalString(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Government venue coordinates must be numeric.');
        }

        return $value;
    }

    private function venueType(mixed $value): FishingVenueType
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return FishingVenueType::OtherPublic;
        }

        return FishingVenueType::tryFrom(Str::upper($value)) ?? FishingVenueType::OtherPublic;
    }

    private function nullableDate(mixed $value): ?CarbonInterface
    {
        $value = $this->nullableString($value);

        return $value === null ? null : CarbonImmutable::parse($value);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'lowisko' : $base;
        $slug = $base;
        $counter = 2;

        while (FishingVenue::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $normalized): array
    {
        $sourceUpdatedAt = $normalized['source_updated_at'] ?? null;

        return [
            'external_id' => $normalized['external_id'],
            'name' => $normalized['name'],
            'description' => $normalized['description'],
            'venue_type' => $normalized['venue_type']->value,
            'latitude' => $normalized['latitude'],
            'longitude' => $normalized['longitude'],
            'address' => $normalized['address'],
            'city' => $normalized['city'],
            'postal_code' => $normalized['postal_code'],
            'voivodeship' => $normalized['voivodeship'],
            'country' => $normalized['country'],
            'source_updated_at' => $sourceUpdatedAt instanceof CarbonInterface ? $sourceUpdatedAt->toIso8601String() : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{list<string>, int}
     */
    private function quality(array $payload): array
    {
        $flags = [];
        $score = 100;

        if ($payload['latitude'] === null || $payload['longitude'] === null) {
            $flags[] = 'missing_coordinates';
            $score -= 35;
        }

        if ($payload['city'] === null) {
            $flags[] = 'missing_city';
            $score -= 15;
        }

        if ($payload['address'] === null) {
            $flags[] = 'missing_address';
            $score -= 10;
        }

        if ($payload['description'] === null) {
            $flags[] = 'missing_description';
            $score -= 10;
        }

        return [$flags, max(0, $score)];
    }
}
