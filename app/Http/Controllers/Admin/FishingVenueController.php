<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingSpotMedia;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use App\Modules\Fisheries\Support\FishingMediaPayload;
use App\Support\CsvStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FishingVenueController extends Controller
{
    public function __construct(private readonly FishingMediaPayload $fishingMediaPayload) {}

    public function index(Request $request): InertiaResponse
    {
        $filters = $this->validatedFilters($request);
        $venues = $this->filteredQuery($filters)
            ->with([
                'organization',
                'moderator',
                'media' => fn ($query) => $query->with('hiddenBy')->orderBy('sort_order')->orderBy('id'),
                'spots' => fn ($query) => $query
                    ->with(['media' => fn ($query) => $query->with('hiddenBy')->orderBy('sort_order')->orderBy('id')])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->withCount([
                'spots as spots_count',
                'bookings as bookings_count',
                'reviews as reviews_count',
                'media as visible_media_count' => fn ($query) => $query->whereNull('hidden_at'),
                'media as hidden_media_count' => fn ($query) => $query->whereNotNull('hidden_at'),
            ])
            ->latest()
            ->limit(80)
            ->get();

        return Inertia::render('admin/fishing-venues/index', [
            'venues' => $venues->map(fn (FishingVenue $venue): array => $this->venuePayload($venue))->values()->all(),
            'filters' => $filters,
            'statuses' => array_map(fn (FishingVenueStatus $status): string => $status->value, FishingVenueStatus::cases()),
            'commercial_statuses' => array_map(fn (FishingVenueCommercialStatus $status): string => $status->value, FishingVenueCommercialStatus::cases()),
            'export_url' => route('admin.fishing-venues.export', array_filter($filters, fn ($value): bool => $value !== null && $value !== '')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $venues = $this->filteredQuery($filters)
            ->with('organization')
            ->withCount(['spots as spots_count', 'bookings as bookings_count', 'media as media_count'])
            ->latest()
            ->limit(5000)
            ->get();

        return CsvStream::download('admin-fishing-venues.csv', [
            'uuid',
            'name',
            'slug',
            'organization',
            'status',
            'commercial_status',
            'city',
            'voivodeship',
            'spots_count',
            'bookings_count',
            'media_count',
            'moderation_reason',
            'created_at',
        ], $venues->map(fn (FishingVenue $venue): array => [
            $venue->uuid,
            $venue->name,
            $venue->slug,
            $venue->organization?->name,
            $venue->status->value,
            $venue->commercial_status->value,
            $venue->city,
            $venue->voivodeship,
            (string) ($venue->getAttribute('spots_count') ?? 0),
            (string) ($venue->getAttribute('bookings_count') ?? 0),
            (string) ($venue->getAttribute('media_count') ?? 0),
            $venue->moderation_reason,
            $venue->created_at?->toIso8601String(),
        ]));
    }

    public function moderate(
        Request $request,
        FishingVenue $fishingVenue,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(FishingVenueStatus::class)],
            'moderation_reason' => ['nullable', 'required_if:status,'.FishingVenueStatus::Suspended->value, 'string', 'max:500'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $before = [
            'status' => $fishingVenue->status->value,
            'moderation_reason' => $fishingVenue->moderation_reason,
        ];

        $status = FishingVenueStatus::from((string) $validated['status']);
        $fishingVenue->forceFill([
            'status' => $status,
            'published_at' => $status === FishingVenueStatus::Published
                ? ($fishingVenue->published_at ?? now())
                : $fishingVenue->published_at,
            'moderation_reason' => $validated['moderation_reason'] ?? null,
            'moderated_by_user_id' => $user->id,
            'moderated_at' => now(),
        ])->save();

        $recordAuditLog->handle(
            action: 'fishing_venue.moderated',
            subject: $fishingVenue,
            actor: $user,
            organization: $fishingVenue->organization,
            before: $before,
            after: [
                'status' => $fishingVenue->status->value,
                'moderation_reason' => $fishingVenue->moderation_reason,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Status łowiska został zapisany.',
        ]);
    }

    public function moderateVenueMedia(
        Request $request,
        FishingVenueMedia $media,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $validated = $request->validate([
            'hidden' => ['required', 'boolean'],
            'hidden_reason' => ['nullable', 'required_if:hidden,1', 'string', 'max:500'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $media->loadMissing('venue.organization');
        $hidden = (bool) $validated['hidden'];
        $before = [
            'hidden_at' => $media->hidden_at?->toIso8601String(),
            'hidden_reason' => $media->hidden_reason,
        ];

        $media->forceFill([
            'hidden_at' => $hidden ? now() : null,
            'hidden_reason' => $hidden ? ($validated['hidden_reason'] ?? null) : null,
            'hidden_by_user_id' => $hidden ? $user->id : null,
        ])->save();

        $recordAuditLog->handle(
            action: 'fishing_venue_media.moderated',
            subject: $media,
            actor: $user,
            organization: $media->venue->organization,
            before: $before,
            after: [
                'hidden_at' => $media->hidden_at?->toIso8601String(),
                'hidden_reason' => $media->hidden_reason,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Widoczność zdjęcia została zapisana.',
        ]);
    }

    public function moderateSpotMedia(
        Request $request,
        FishingSpotMedia $media,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $validated = $request->validate([
            'hidden' => ['required', 'boolean'],
            'hidden_reason' => ['nullable', 'required_if:hidden,1', 'string', 'max:500'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $media->loadMissing('spot.venue.organization');
        $hidden = (bool) $validated['hidden'];
        $before = [
            'hidden_at' => $media->hidden_at?->toIso8601String(),
            'hidden_reason' => $media->hidden_reason,
        ];

        $media->forceFill([
            'hidden_at' => $hidden ? now() : null,
            'hidden_reason' => $hidden ? ($validated['hidden_reason'] ?? null) : null,
            'hidden_by_user_id' => $hidden ? $user->id : null,
        ])->save();

        $recordAuditLog->handle(
            action: 'fishing_spot_media.moderated',
            subject: $media,
            actor: $user,
            organization: $media->spot->venue->organization,
            before: $before,
            after: [
                'hidden_at' => $media->hidden_at?->toIso8601String(),
                'hidden_reason' => $media->hidden_reason,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Widoczność zdjęcia stanowiska została zapisana.',
        ]);
    }

    /**
     * @return array{q: string|null, status: string|null, commercial_status: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'commercial_status' => ['nullable', 'string'],
        ]);

        $status = $validated['status'] ?? null;
        if (! in_array($status, array_map(fn (FishingVenueStatus $status): string => $status->value, FishingVenueStatus::cases()), true)) {
            $status = null;
        }

        $commercialStatus = $validated['commercial_status'] ?? null;
        if (! in_array($commercialStatus, array_map(fn (FishingVenueCommercialStatus $status): string => $status->value, FishingVenueCommercialStatus::cases()), true)) {
            $commercialStatus = null;
        }

        return [
            'q' => $validated['q'] ?? null,
            'status' => $status,
            'commercial_status' => $commercialStatus,
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<FishingVenue>
     */
    private function filteredQuery(array $filters): Builder
    {
        return FishingVenue::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('voivodeship', 'like', "%{$search}%")
                        ->orWhere('uuid', $search)
                        ->orWhereHas('organization', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['commercial_status'] ?? null, fn (Builder $query, string $status) => $query->where('commercial_status', $status));
    }

    /**
     * @return array<string, mixed>
     */
    private function venuePayload(FishingVenue $venue): array
    {
        return [
            'uuid' => $venue->uuid,
            'name' => $venue->name,
            'slug' => $venue->slug,
            'status' => $venue->status->value,
            'commercial_status' => $venue->commercial_status->value,
            'city' => $venue->city,
            'voivodeship' => $venue->voivodeship,
            'moderation_reason' => $venue->moderation_reason,
            'moderated_at' => $venue->moderated_at?->toIso8601String(),
            'organization' => $venue->organization === null ? null : [
                'uuid' => $venue->organization->uuid,
                'name' => $venue->organization->name,
            ],
            'moderator' => $venue->moderator === null ? null : [
                'name' => $venue->moderator->name,
                'email' => $venue->moderator->email,
            ],
            'spots_count' => (int) ($venue->getAttribute('spots_count') ?? 0),
            'bookings_count' => (int) ($venue->getAttribute('bookings_count') ?? 0),
            'reviews_count' => (int) ($venue->getAttribute('reviews_count') ?? 0),
            'visible_media_count' => (int) ($venue->getAttribute('visible_media_count') ?? 0),
            'hidden_media_count' => (int) ($venue->getAttribute('hidden_media_count') ?? 0),
            'media' => $venue->media
                ->take(8)
                ->map(fn (FishingVenueMedia $media): array => $this->venueMediaPayload($media))
                ->values()
                ->all(),
            'spot_media' => $venue->spots
                ->flatMap(fn (FishingSpot $spot) => $spot->media->map(fn (FishingSpotMedia $media): array => $this->spotMediaPayload($spot, $media)))
                ->take(8)
                ->values()
                ->all(),
            'created_at' => $venue->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function venueMediaPayload(FishingVenueMedia $media): array
    {
        return [
            ...$this->fishingMediaPayload->venue($media),
            'hidden_at' => $media->hidden_at?->toIso8601String(),
            'hidden_reason' => $media->hidden_reason,
            'hidden_by' => $media->hiddenBy === null ? null : [
                'name' => $media->hiddenBy->name,
                'email' => $media->hiddenBy->email,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spotMediaPayload(FishingSpot $spot, FishingSpotMedia $media): array
    {
        return [
            ...$this->fishingMediaPayload->spot($media),
            'spot_name' => $spot->name,
            'hidden_at' => $media->hidden_at?->toIso8601String(),
            'hidden_reason' => $media->hidden_reason,
            'hidden_by' => $media->hiddenBy === null ? null : [
                'name' => $media->hiddenBy->name,
                'email' => $media->hiddenBy->email,
            ],
        ];
    }
}
