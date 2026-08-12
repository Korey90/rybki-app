<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\GovernmentData\Actions\ResolveGovernmentVenueRecord;
use App\Modules\GovernmentData\Actions\SyncDaneGovPlFishingDistricts;
use App\Modules\GovernmentData\Enums\GovernmentDataSyncRunStatus;
use App\Modules\GovernmentData\Enums\GovernmentVenueRecordStatus;
use App\Modules\GovernmentData\Models\DataSource;
use App\Modules\GovernmentData\Models\GovernmentDataSyncRun;
use App\Modules\GovernmentData\Models\GovernmentVenueRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GovernmentDataController extends Controller
{
    /**
     * Show imported public data sources and latest normalized records.
     */
    public function index(): Response
    {
        $sources = DataSource::query()
            ->withCount(['governmentVenueRecords', 'venueSources', 'syncRuns'])
            ->orderBy('provider')
            ->orderBy('dataset_name')
            ->get();

        $records = GovernmentVenueRecord::query()
            ->with(['dataSource', 'importedVenue', 'matchedVenue', 'reviewedBy'])
            ->latest()
            ->limit(100)
            ->get();

        $syncRuns = GovernmentDataSyncRun::query()
            ->with('dataSource')
            ->latest('started_at')
            ->limit(20)
            ->get();

        $candidateVenues = FishingVenue::query()
            ->orderBy('name')
            ->limit(200)
            ->get();

        return Inertia::render('admin/government-data/index', [
            'sources' => $sources->map(fn (DataSource $source): array => [
                'id' => $source->id,
                'provider' => $source->provider,
                'dataset_name' => $source->dataset_name,
                'dataset_url' => $source->dataset_url,
                'external_dataset_id' => $source->external_dataset_id,
                'default_format' => $source->default_format,
                'sync_enabled' => $source->sync_enabled,
                'last_successful_sync_at' => $source->last_successful_sync_at?->toIso8601String(),
                'records_count' => $source->government_venue_records_count,
                'venue_sources_count' => $source->venue_sources_count,
                'sync_runs_count' => $source->sync_runs_count,
            ])->values()->all(),
            'sync_runs' => $syncRuns->map(fn (GovernmentDataSyncRun $run): array => [
                'id' => $run->id,
                'status' => $run->status->value,
                'resource_external_id' => $run->resource_external_id,
                'resource_title' => $run->resource_title,
                'resource_url' => $run->resource_url,
                'started_at' => $run->started_at->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'records_seen' => $run->records_seen,
                'records_staged' => $run->records_staged,
                'records_needing_review' => $run->records_needing_review,
                'records_with_candidates' => $run->records_with_candidates,
                'records_skipped' => $run->records_skipped,
                'error' => $run->error,
                'data_source' => [
                    'provider' => $run->dataSource->provider,
                    'dataset_name' => $run->dataSource->dataset_name,
                ],
            ])->values()->all(),
            'status_counts' => $this->statusCounts(),
            'records' => $records->map(fn (GovernmentVenueRecord $record): array => $this->recordPayload($record))->values()->all(),
            'candidate_venues' => $candidateVenues->map(fn (FishingVenue $venue): array => [
                'uuid' => $venue->uuid,
                'name' => $venue->name,
                'city' => $venue->city,
                'voivodeship' => $venue->voivodeship,
                'commercial_status' => $venue->commercial_status->value,
                'organization_id' => $venue->organization_id,
            ])->values()->all(),
        ]);
    }

    public function sync(Request $request, SyncDaneGovPlFishingDistricts $sync): RedirectResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $run = $sync->handle(isset($validated['limit']) ? (int) $validated['limit'] : null);

        if ($run->status === GovernmentDataSyncRunStatus::Failed) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Sync danych publicznych nie powiódł się.',
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Sync zakończony. Do decyzji: {$run->records_needing_review}.",
        ]);
    }

    public function import(
        Request $request,
        GovernmentVenueRecord $record,
        ResolveGovernmentVenueRecord $resolveRecord
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $resolveRecord->importAsVenue($record, $user);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Rekord publiczny został opublikowany jako profil informacyjny.',
        ]);
    }

    public function match(
        Request $request,
        GovernmentVenueRecord $record,
        ResolveGovernmentVenueRecord $resolveRecord
    ): RedirectResponse {
        $validated = $request->validate([
            'fishing_venue_uuid' => ['required', 'string', 'exists:fishing_venues,uuid'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        /** @var FishingVenue $venue */
        $venue = FishingVenue::query()
            ->where('uuid', $validated['fishing_venue_uuid'])
            ->firstOrFail();

        $resolveRecord->matchVenue($record, $venue, $user);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Rekord publiczny został połączony z istniejącym łowiskiem.',
        ]);
    }

    public function skip(
        Request $request,
        GovernmentVenueRecord $record,
        ResolveGovernmentVenueRecord $resolveRecord
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $resolveRecord->skip($record, $user);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Rekord publiczny został pominięty.',
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function statusCounts(): array
    {
        return collect(GovernmentVenueRecordStatus::cases())
            ->mapWithKeys(fn (GovernmentVenueRecordStatus $status): array => [
                $status->value => GovernmentVenueRecord::query()->where('status', $status->value)->count(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(GovernmentVenueRecord $record): array
    {
        $venue = $record->importedVenue ?? $record->matchedVenue;

        return [
            'id' => $record->id,
            'external_id' => $record->external_id,
            'name' => $record->name,
            'city' => $record->city,
            'voivodeship' => $record->voivodeship,
            'venue_type' => $record->venue_type->value,
            'status' => $record->status->value,
            'source_updated_at' => $record->source_updated_at?->toIso8601String(),
            'quality_flags' => $record->quality_flags ?? [],
            'quality_score' => $record->quality_score,
            'match_candidates' => $record->match_candidates ?? [],
            'match_confidence' => $record->match_confidence,
            'reviewed_at' => $record->reviewed_at?->toIso8601String(),
            'reviewed_by' => $record->reviewedBy?->name,
            'data_source' => [
                'provider' => $record->dataSource->provider,
                'dataset_name' => $record->dataSource->dataset_name,
            ],
            'venue' => $venue === null ? null : [
                'uuid' => $venue->uuid,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'commercial_status' => $venue->commercial_status->value,
            ],
        ];
    }
}
