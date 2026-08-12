<?php

namespace App\Jobs;

use App\Modules\GovernmentData\Actions\SyncDaneGovPlFishingDistricts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGovernmentDataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ?int $limit = null) {}

    public function handle(SyncDaneGovPlFishingDistricts $sync): void
    {
        $sync->handle($this->limit);
    }
}
