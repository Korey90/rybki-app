<?php

namespace App\Modules\Fisheries\Queries;

use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FishingVenueGeoQuery
{
    public const DEFAULT_RADIUS_KM = 50;

    public const MAX_RADIUS_KM = 300;

    /**
     * @param  Builder<FishingVenue>  $query
     * @return Builder<FishingVenue>
     */
    public function apply(Builder $query, ?float $latitude, ?float $longitude, ?int $radiusKm, bool $sortByDistance): Builder
    {
        if (! $this->hasCoordinates($latitude, $longitude)) {
            return $query;
        }

        $radiusKm = $this->normalizeRadius($radiusKm);

        if ($this->supportsPostgis()) {
            return $this->applyPostgis($query, (float) $latitude, (float) $longitude, $radiusKm, $sortByDistance);
        }

        return $this->applyBoundingBox($query, (float) $latitude, (float) $longitude, $radiusKm);
    }

    public function distanceKm(FishingVenue $venue, ?float $latitude, ?float $longitude): ?float
    {
        if (! $this->hasCoordinates($latitude, $longitude)
            || $venue->latitude === null
            || $venue->longitude === null) {
            return null;
        }

        $earthRadiusKm = 6371.0;
        $venueLatitude = deg2rad((float) $venue->latitude);
        $venueLongitude = deg2rad((float) $venue->longitude);
        $originLatitude = deg2rad((float) $latitude);
        $originLongitude = deg2rad((float) $longitude);

        $latitudeDelta = $venueLatitude - $originLatitude;
        $longitudeDelta = $venueLongitude - $originLongitude;

        $a = sin($latitudeDelta / 2) ** 2
            + cos($originLatitude) * cos($venueLatitude) * sin($longitudeDelta / 2) ** 2;

        return round($earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    private function supportsPostgis(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql'
            && Schema::hasColumn('fishing_venues', 'location');
    }

    private function hasCoordinates(?float $latitude, ?float $longitude): bool
    {
        return $latitude !== null
            && $longitude !== null
            && $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;
    }

    private function normalizeRadius(?int $radiusKm): int
    {
        if ($radiusKm === null || $radiusKm < 1) {
            return self::DEFAULT_RADIUS_KM;
        }

        return min($radiusKm, self::MAX_RADIUS_KM);
    }

    /**
     * @param  Builder<FishingVenue>  $query
     * @return Builder<FishingVenue>
     */
    private function applyPostgis(Builder $query, float $latitude, float $longitude, int $radiusKm, bool $sortByDistance): Builder
    {
        $pointExpression = 'ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography';
        $radiusMeters = $radiusKm * 1000;

        $query
            ->select('fishing_venues.*')
            ->selectRaw("ST_Distance(location, {$pointExpression}) / 1000 as distance_km", [$longitude, $latitude])
            ->whereNotNull('location')
            ->whereRaw("ST_DWithin(location, {$pointExpression}, ?)", [$longitude, $latitude, $radiusMeters]);

        if ($sortByDistance) {
            $query->orderBy('distance_km');
        }

        return $query;
    }

    /**
     * @param  Builder<FishingVenue>  $query
     * @return Builder<FishingVenue>
     */
    private function applyBoundingBox(Builder $query, float $latitude, float $longitude, int $radiusKm): Builder
    {
        $latitudeDelta = $radiusKm / 111;
        $longitudeDelta = $radiusKm / max(1, 111 * cos(deg2rad($latitude)));

        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta]);
    }
}
