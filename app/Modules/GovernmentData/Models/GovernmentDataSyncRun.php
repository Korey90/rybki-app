<?php

namespace App\Modules\GovernmentData\Models;

use App\Modules\GovernmentData\Enums\GovernmentDataSyncRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $data_source_id
 * @property GovernmentDataSyncRunStatus $status
 * @property string|null $resource_external_id
 * @property string|null $resource_title
 * @property string|null $resource_url
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int $records_seen
 * @property int $records_staged
 * @property int $records_needing_review
 * @property int $records_with_candidates
 * @property int $records_skipped
 * @property string|null $error
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'data_source_id',
    'status',
    'resource_external_id',
    'resource_title',
    'resource_url',
    'started_at',
    'finished_at',
    'records_seen',
    'records_staged',
    'records_needing_review',
    'records_with_candidates',
    'records_skipped',
    'error',
    'metadata',
])]
class GovernmentDataSyncRun extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GovernmentDataSyncRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DataSource, $this>
     */
    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
