<?php

namespace App\Modules\GovernmentData\Actions;

use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Enums\GovernmentVenueRecordStatus;
use App\Modules\GovernmentData\Models\FishingVenueSource;
use App\Modules\GovernmentData\Models\GovernmentVenueRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResolveGovernmentVenueRecord
{
    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}

    public function importAsVenue(GovernmentVenueRecord $record, User $actor): FishingVenue
    {
        return DB::transaction(function () use ($actor, $record): FishingVenue {
            $venue = $record->importedVenue()->first();

            if (! $venue instanceof FishingVenue) {
                $venue = FishingVenue::create([
                    'organization_id' => null,
                    'name' => $record->name,
                    'slug' => $this->uniqueSlug($record->name),
                    'description' => $record->description,
                    'venue_type' => $record->venue_type,
                    'commercial_status' => FishingVenueCommercialStatus::InformationOnly,
                    'latitude' => $record->latitude,
                    'longitude' => $record->longitude,
                    'address' => $record->address,
                    'city' => $record->city,
                    'postal_code' => $record->postal_code,
                    'voivodeship' => $record->voivodeship,
                    'country' => $record->country,
                    'status' => FishingVenueStatus::Published,
                    'published_at' => now(),
                ]);
            }

            $this->linkSource($record, $venue);

            $record->fill([
                'status' => GovernmentVenueRecordStatus::Imported,
                'matched_fishing_venue_id' => $venue->id,
                'imported_fishing_venue_id' => $venue->id,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'imported_at' => now(),
                'skipped_at' => null,
            ])->save();

            $this->recordAuditLog->handle(
                action: 'government_data.record_imported',
                subject: $record,
                actor: $actor,
                after: [
                    'record_id' => $record->id,
                    'venue_id' => $venue->id,
                ],
            );

            return $venue;
        });
    }

    public function matchVenue(GovernmentVenueRecord $record, FishingVenue $venue, User $actor): void
    {
        DB::transaction(function () use ($actor, $record, $venue): void {
            $this->linkSource($record, $venue);

            $record->fill([
                'status' => GovernmentVenueRecordStatus::Matched,
                'matched_fishing_venue_id' => $venue->id,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'skipped_at' => null,
            ])->save();

            $this->recordAuditLog->handle(
                action: 'government_data.record_matched',
                subject: $record,
                actor: $actor,
                after: [
                    'record_id' => $record->id,
                    'venue_id' => $venue->id,
                    'venue_organization_id' => $venue->organization_id,
                ],
            );
        });
    }

    public function skip(GovernmentVenueRecord $record, User $actor): void
    {
        $record->fill([
            'status' => GovernmentVenueRecordStatus::Skipped,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'skipped_at' => now(),
        ])->save();

        $this->recordAuditLog->handle(
            action: 'government_data.record_skipped',
            subject: $record,
            actor: $actor,
            after: [
                'record_id' => $record->id,
            ],
        );
    }

    private function linkSource(GovernmentVenueRecord $record, FishingVenue $venue): void
    {
        FishingVenueSource::query()->updateOrCreate(
            [
                'data_source_id' => $record->data_source_id,
                'external_id' => $record->external_id,
            ],
            [
                'fishing_venue_id' => $venue->id,
                'source_payload' => $record->source_payload,
                'source_updated_at' => $record->source_updated_at,
                'last_synced_at' => now(),
            ],
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base === '' ? 'lowisko-publiczne' : $base;
        $slug = $base;
        $counter = 2;

        while (FishingVenue::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
