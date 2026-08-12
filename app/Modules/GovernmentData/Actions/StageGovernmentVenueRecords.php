<?php

namespace App\Modules\GovernmentData\Actions;

use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\GovernmentData\Enums\GovernmentVenueRecordStatus;
use App\Modules\GovernmentData\Models\DataSource;
use App\Modules\GovernmentData\Models\GovernmentVenueRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class StageGovernmentVenueRecords
{
    public function __construct(
        private readonly NormalizeGovernmentVenueRecord $normalizer,
        private readonly FindGovernmentVenueMatches $matcher,
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{records:int, staged:int, needs_review:int, with_candidates:int, skipped:int}
     */
    public function handle(DataSource $dataSource, array $records): array
    {
        $result = [
            'records' => count($records),
            'staged' => 0,
            'needs_review' => 0,
            'with_candidates' => 0,
            'skipped' => 0,
        ];

        foreach ($records as $record) {
            $normalized = $this->normalizer->handle($record);
            $candidates = $this->matcher->handle($normalized['normalized_payload']);
            $status = $this->statusFor($normalized['quality_flags'], $candidates);
            $sourcePayload = Arr::get($record, '_source_payload');
            $sourcePayload = is_array($sourcePayload) ? $sourcePayload : $record;

            DB::transaction(function () use ($candidates, $dataSource, $normalized, $sourcePayload, $status, &$result): void {
                $governmentRecord = GovernmentVenueRecord::query()->firstOrNew([
                    'data_source_id' => $dataSource->id,
                    'external_id' => $normalized['external_id'],
                ]);

                $finalStatus = in_array($governmentRecord->status, [
                    GovernmentVenueRecordStatus::Imported,
                    GovernmentVenueRecordStatus::Matched,
                    GovernmentVenueRecordStatus::Skipped,
                ], true)
                    ? $governmentRecord->status
                    : $status;

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
                    'source_payload' => $sourcePayload,
                    'normalized_payload' => $normalized['normalized_payload'],
                    'quality_flags' => $normalized['quality_flags'],
                    'quality_score' => $normalized['quality_score'],
                    'match_candidates' => $candidates,
                    'match_confidence' => (int) ($candidates[0]['score'] ?? 0),
                    'source_updated_at' => $normalized['source_updated_at'],
                    'status' => $finalStatus,
                ])->save();

                $result['staged']++;

                if ($finalStatus === GovernmentVenueRecordStatus::NeedsReview || $finalStatus === GovernmentVenueRecordStatus::Pending) {
                    $result['needs_review']++;
                } elseif ($finalStatus === GovernmentVenueRecordStatus::Skipped) {
                    $result['skipped']++;
                }

                if ($candidates !== []) {
                    $result['with_candidates']++;
                }
            });
        }

        $dataSource->forceFill(['last_successful_sync_at' => now()])->save();

        $this->recordAuditLog->handle(
            action: 'government_data.records_staged',
            subject: $dataSource,
            after: [
                'provider' => $dataSource->provider,
                'dataset_name' => $dataSource->dataset_name,
                ...$result,
            ],
        );

        return $result;
    }

    /**
     * @param  list<string>  $qualityFlags
     * @param  list<array<string, mixed>>  $candidates
     */
    private function statusFor(array $qualityFlags, array $candidates): GovernmentVenueRecordStatus
    {
        if ($candidates !== [] || $qualityFlags !== []) {
            return GovernmentVenueRecordStatus::NeedsReview;
        }

        return GovernmentVenueRecordStatus::Pending;
    }
}
