<?php

namespace App\Modules\GovernmentData\Actions;

use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\GovernmentData\Enums\GovernmentDataSyncRunStatus;
use App\Modules\GovernmentData\Models\GovernmentDataSyncRun;
use App\Modules\GovernmentData\Sources\DaneGovPlFishingDistrictsSource;
use Throwable;

class SyncDaneGovPlFishingDistricts
{
    public function __construct(
        private readonly DaneGovPlFishingDistrictsSource $source,
        private readonly StageGovernmentVenueRecords $stageRecords,
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(?int $limit = null): GovernmentDataSyncRun
    {
        $dataSource = $this->source->dataSource();

        /** @var GovernmentDataSyncRun $run */
        $run = GovernmentDataSyncRun::query()->create([
            'data_source_id' => $dataSource->id,
            'status' => GovernmentDataSyncRunStatus::Running,
            'started_at' => now(),
            'metadata' => [
                'limit' => $limit,
            ],
        ]);

        try {
            $payload = $this->source->fetch($limit);
            $result = $this->stageRecords->handle($payload['data_source'], $payload['records']);
            $resource = $payload['resource'];

            $run->fill([
                'status' => GovernmentDataSyncRunStatus::Completed,
                'resource_external_id' => (string) ($resource['id'] ?? ''),
                'resource_title' => (string) ($resource['title'] ?? ''),
                'resource_url' => (string) ($resource['url'] ?? ''),
                'finished_at' => now(),
                'records_seen' => $result['records'],
                'records_staged' => $result['staged'],
                'records_needing_review' => $result['needs_review'],
                'records_with_candidates' => $result['with_candidates'],
                'records_skipped' => $result['skipped'],
                'metadata' => [
                    'resource_data_date' => $resource['data_date'] ?? null,
                    'resource_modified' => $resource['modified'] ?? null,
                    'limit' => $limit,
                ],
            ])->save();

            $this->recordAuditLog->handle(
                action: 'government_data.sync_completed',
                subject: $run,
                after: [
                    'data_source_id' => $dataSource->id,
                    'records_seen' => $run->records_seen,
                    'records_needing_review' => $run->records_needing_review,
                    'resource_external_id' => $run->resource_external_id,
                ],
            );
        } catch (Throwable $exception) {
            $run->fill([
                'status' => GovernmentDataSyncRunStatus::Failed,
                'finished_at' => now(),
                'error' => $exception->getMessage(),
            ])->save();

            $this->recordAuditLog->handle(
                action: 'government_data.sync_failed',
                subject: $run,
                after: [
                    'data_source_id' => $dataSource->id,
                    'error' => $exception->getMessage(),
                ],
            );
        }

        return $run->refresh();
    }
}
