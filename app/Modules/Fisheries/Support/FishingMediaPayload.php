<?php

namespace App\Modules\Fisheries\Support;

use App\Modules\Fisheries\Models\FishingSpotMedia;
use App\Modules\Fisheries\Models\FishingVenueMedia;
use Illuminate\Support\Facades\Storage;

class FishingMediaPayload
{
    /**
     * @return array<string, bool|int|string|null>
     */
    public function venue(FishingVenueMedia $media, bool $isPrimary = false): array
    {
        return [
            'id' => $media->id,
            'url' => $this->publicUrl($media->storage_path),
            'storage_path' => $media->storage_path,
            'type' => $media->type->value,
            'sort_order' => $media->sort_order,
            'is_primary' => $isPrimary,
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function spot(FishingSpotMedia $media, bool $isPrimary = false): array
    {
        return [
            'id' => $media->id,
            'url' => $this->publicUrl($media->storage_path),
            'storage_path' => $media->storage_path,
            'sort_order' => $media->sort_order,
            'is_primary' => $isPrimary,
        ];
    }

    private function publicUrl(string $storagePath): ?string
    {
        if (! Storage::disk('public')->exists($storagePath)) {
            return null;
        }

        return Storage::disk('public')->url($storagePath);
    }
}
