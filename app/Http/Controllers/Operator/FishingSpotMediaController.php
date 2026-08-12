<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\ReorderFishingSpotMediaRequest;
use App\Http\Requests\Operator\StoreFishingSpotMediaRequest;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingSpotMedia;
use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FishingSpotMediaController extends Controller
{
    /**
     * Upload images to an operator-managed fishing spot.
     */
    public function store(
        StoreFishingSpotMediaRequest $request,
        FishingVenue $fishingVenue,
        FishingSpot $fishingSpot
    ): RedirectResponse {
        $this->authorizeSpot($fishingVenue, $fishingSpot);

        /** @var array<int, UploadedFile> $files */
        $files = array_values(array_filter(
            (array) $request->file('images', []),
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        if ($fishingSpot->media()->count() + count($files) > StoreFishingSpotMediaRequest::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'Galeria stanowiska może mieć maksymalnie '.StoreFishingSpotMediaRequest::MAX_IMAGES.' zdjęć.',
            ]);
        }

        $nextSortOrder = (int) $fishingSpot->media()->max('sort_order') + 1;

        foreach ($files as $file) {
            $path = $file->storePublicly("fishing-spots/{$fishingSpot->uuid}", 'public');

            if (! is_string($path)) {
                throw ValidationException::withMessages([
                    'images' => 'Nie udało się zapisać zdjęcia. Spróbuj ponownie.',
                ]);
            }

            $fishingSpot->media()->create([
                'storage_path' => $path,
                'sort_order' => $nextSortOrder++,
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Zdjęcia stanowiska zostały dodane.',
        ]);
    }

    /**
     * Reorder fishing spot media records.
     */
    public function reorder(
        ReorderFishingSpotMediaRequest $request,
        FishingVenue $fishingVenue,
        FishingSpot $fishingSpot
    ): RedirectResponse {
        $this->authorizeSpot($fishingVenue, $fishingSpot);

        $mediaIds = $this->validatedMediaIds($request->validated('media_ids'));
        $this->assertSpotOwnsMediaIds($fishingSpot, $mediaIds);
        $this->applyOrder($fishingSpot, $mediaIds);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kolejność zdjęć stanowiska została zapisana.',
        ]);
    }

    /**
     * Mark one spot image as primary by moving it to the first position.
     */
    public function makePrimary(
        FishingVenue $fishingVenue,
        FishingSpot $fishingSpot,
        FishingSpotMedia $media
    ): RedirectResponse {
        $this->authorizeSpot($fishingVenue, $fishingSpot);
        $this->assertSpotOwnsMedia($fishingSpot, $media);

        $orderedIds = $fishingSpot->media()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $media->id)
            ->prepend($media->id)
            ->values()
            ->all();

        $this->applyOrder($fishingSpot, $orderedIds);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Zdjęcie główne stanowiska zostało ustawione.',
        ]);
    }

    /**
     * Delete a spot image.
     */
    public function destroy(
        FishingVenue $fishingVenue,
        FishingSpot $fishingSpot,
        FishingSpotMedia $media
    ): RedirectResponse {
        $this->authorizeSpot($fishingVenue, $fishingSpot);
        $this->assertSpotOwnsMedia($fishingSpot, $media);

        $storagePath = $media->storage_path;
        $media->delete();
        Storage::disk('public')->delete($storagePath);
        $this->applyOrder($fishingSpot, []);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Zdjęcie stanowiska zostało usunięte.',
        ]);
    }

    private function authorizeSpot(FishingVenue $fishingVenue, FishingSpot $fishingSpot): void
    {
        abort_unless($fishingSpot->fishing_venue_id === $fishingVenue->id, 404);
        Gate::authorize('manage', $fishingSpot);
    }

    /**
     * @return array<int, int>
     */
    private function validatedMediaIds(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $mediaIds
     */
    private function assertSpotOwnsMediaIds(FishingSpot $fishingSpot, array $mediaIds): void
    {
        $ownedCount = $fishingSpot->media()
            ->whereIn('id', $mediaIds)
            ->count();

        abort_unless($ownedCount === count($mediaIds), 403);
    }

    private function assertSpotOwnsMedia(FishingSpot $fishingSpot, FishingSpotMedia $media): void
    {
        abort_unless($media->fishing_spot_id === $fishingSpot->id, 404);
    }

    /**
     * @param  array<int, int>  $preferredIds
     */
    private function applyOrder(FishingSpot $fishingSpot, array $preferredIds): void
    {
        $remainingIds = $fishingSpot->media()
            ->whereNotIn('id', $preferredIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ([...$preferredIds, ...$remainingIds] as $index => $mediaId) {
            $fishingSpot->media()
                ->whereKey($mediaId)
                ->update(['sort_order' => $index + 1]);
        }
    }
}
