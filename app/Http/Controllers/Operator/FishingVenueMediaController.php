<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\ReorderFishingVenueMediaRequest;
use App\Http\Requests\Operator\StoreFishingVenueMediaRequest;
use App\Modules\Fisheries\Enums\FishingVenueMediaType;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FishingVenueMediaController extends Controller
{
    /**
     * Upload images to an operator-managed fishing venue.
     */
    public function store(StoreFishingVenueMediaRequest $request, FishingVenue $fishingVenue): RedirectResponse
    {
        Gate::authorize('update', $fishingVenue);

        /** @var array<int, UploadedFile> $files */
        $files = array_values(array_filter(
            (array) $request->file('images', []),
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        if ($fishingVenue->media()->count() + count($files) > StoreFishingVenueMediaRequest::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'Galeria łowiska może mieć maksymalnie '.StoreFishingVenueMediaRequest::MAX_IMAGES.' zdjęć.',
            ]);
        }

        $nextSortOrder = (int) $fishingVenue->media()->max('sort_order') + 1;

        foreach ($files as $file) {
            $path = $file->storePublicly("fishing-venues/{$fishingVenue->uuid}", 'public');

            if (! is_string($path)) {
                throw ValidationException::withMessages([
                    'images' => 'Nie udało się zapisać zdjęcia. Spróbuj ponownie.',
                ]);
            }

            $fishingVenue->media()->create([
                'storage_path' => $path,
                'type' => FishingVenueMediaType::Image,
                'sort_order' => $nextSortOrder++,
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Zdjęcia łowiska zostały dodane.',
        ]);
    }

    /**
     * Reorder fishing venue media records.
     */
    public function reorder(ReorderFishingVenueMediaRequest $request, FishingVenue $fishingVenue): RedirectResponse
    {
        Gate::authorize('update', $fishingVenue);

        $mediaIds = $this->validatedMediaIds($request->validated('media_ids'));
        $this->assertVenueOwnsMediaIds($fishingVenue, $mediaIds);
        $this->applyOrder($fishingVenue, $mediaIds);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kolejność zdjęć łowiska została zapisana.',
        ]);
    }

    /**
     * Mark one venue image as primary by moving it to the first position.
     */
    public function makePrimary(FishingVenue $fishingVenue, FishingVenueMedia $media): RedirectResponse
    {
        Gate::authorize('update', $fishingVenue);
        $this->assertVenueOwnsMedia($fishingVenue, $media);

        $orderedIds = $fishingVenue->media()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $media->id)
            ->prepend($media->id)
            ->values()
            ->all();

        $this->applyOrder($fishingVenue, $orderedIds);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Zdjęcie główne łowiska zostało ustawione.',
        ]);
    }

    /**
     * Delete a venue image.
     */
    public function destroy(FishingVenue $fishingVenue, FishingVenueMedia $media): RedirectResponse
    {
        Gate::authorize('update', $fishingVenue);
        $this->assertVenueOwnsMedia($fishingVenue, $media);

        $storagePath = $media->storage_path;
        $media->delete();
        Storage::disk('public')->delete($storagePath);
        $this->applyOrder($fishingVenue, []);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Zdjęcie łowiska zostało usunięte.',
        ]);
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
    private function assertVenueOwnsMediaIds(FishingVenue $fishingVenue, array $mediaIds): void
    {
        $ownedCount = $fishingVenue->media()
            ->whereIn('id', $mediaIds)
            ->count();

        abort_unless($ownedCount === count($mediaIds), 403);
    }

    private function assertVenueOwnsMedia(FishingVenue $fishingVenue, FishingVenueMedia $media): void
    {
        abort_unless($media->fishing_venue_id === $fishingVenue->id, 404);
    }

    /**
     * @param  array<int, int>  $preferredIds
     */
    private function applyOrder(FishingVenue $fishingVenue, array $preferredIds): void
    {
        $remainingIds = $fishingVenue->media()
            ->whereNotIn('id', $preferredIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach ([...$preferredIds, ...$remainingIds] as $index => $mediaId) {
            $fishingVenue->media()
                ->whereKey($mediaId)
                ->update(['sort_order' => $index + 1]);
        }
    }
}
