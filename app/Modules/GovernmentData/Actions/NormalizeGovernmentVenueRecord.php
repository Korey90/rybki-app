<?php

namespace App\Modules\GovernmentData\Actions;

use App\Modules\Fisheries\Enums\FishingVenueType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NormalizeGovernmentVenueRecord
{
    /**
     * @param  array<string, mixed>  $record
     * @return array{
     *     external_id:string,
     *     name:string,
     *     description:?string,
     *     venue_type:FishingVenueType,
     *     latitude:?string,
     *     longitude:?string,
     *     address:?string,
     *     city:?string,
     *     postal_code:?string,
     *     voivodeship:?string,
     *     country:string,
     *     source_updated_at:?CarbonInterface,
     *     normalized_payload:array<string, mixed>,
     *     quality_flags:list<string>,
     *     quality_score:int
     * }
     */
    public function handle(array $record): array
    {
        $externalId = $this->nullableString(Arr::get($record, 'external_id'));
        $name = $this->nullableString(Arr::get($record, 'name'));

        if ($externalId === null || $name === null) {
            throw new InvalidArgumentException('Government venue records require external_id and name.');
        }

        $latitude = $this->nullableDecimalString(Arr::get($record, 'latitude'));
        $longitude = $this->nullableDecimalString(Arr::get($record, 'longitude'));
        $description = $this->nullableString(Arr::get($record, 'description'));
        $address = $this->nullableString(Arr::get($record, 'address'));
        $city = $this->nullableString(Arr::get($record, 'city'));
        $postalCode = $this->nullableString(Arr::get($record, 'postal_code'));
        $voivodeship = $this->nullableString(Arr::get($record, 'voivodeship'));
        $country = Str::upper($this->nullableString(Arr::get($record, 'country')) ?? 'PL');
        $venueType = $this->venueType(Arr::get($record, 'venue_type'));
        $sourceUpdatedAt = $this->nullableDate(Arr::get($record, 'source_updated_at'));

        $normalizedPayload = [
            'external_id' => $externalId,
            'name' => $name,
            'description' => $description,
            'venue_type' => $venueType->value,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address,
            'city' => $city,
            'postal_code' => $postalCode,
            'voivodeship' => $voivodeship,
            'country' => $country,
            'source_updated_at' => $sourceUpdatedAt?->toIso8601String(),
        ];

        [$qualityFlags, $qualityScore] = $this->quality($normalizedPayload);

        return [
            'external_id' => $externalId,
            'name' => $name,
            'description' => $description,
            'venue_type' => $venueType,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address,
            'city' => $city,
            'postal_code' => $postalCode,
            'voivodeship' => $voivodeship,
            'country' => $country,
            'source_updated_at' => $sourceUpdatedAt,
            'normalized_payload' => $normalizedPayload,
            'quality_flags' => $qualityFlags,
            'quality_score' => $qualityScore,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }

    private function nullableDecimalString(mixed $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Government venue coordinates must be numeric.');
        }

        return $value;
    }

    private function venueType(mixed $value): FishingVenueType
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return FishingVenueType::OtherPublic;
        }

        return FishingVenueType::tryFrom(Str::upper($value)) ?? FishingVenueType::OtherPublic;
    }

    private function nullableDate(mixed $value): ?CarbonInterface
    {
        $value = $this->nullableString($value);

        return $value === null ? null : CarbonImmutable::parse($value);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{list<string>, int}
     */
    private function quality(array $payload): array
    {
        $flags = [];
        $score = 100;

        if ($payload['latitude'] === null || $payload['longitude'] === null) {
            $flags[] = 'missing_coordinates';
            $score -= 35;
        }

        if ($payload['city'] === null) {
            $flags[] = 'missing_city';
            $score -= 15;
        }

        if ($payload['address'] === null) {
            $flags[] = 'missing_address';
            $score -= 10;
        }

        if ($payload['description'] === null) {
            $flags[] = 'missing_description';
            $score -= 10;
        }

        if (mb_strlen((string) $payload['name']) < 4) {
            $flags[] = 'weak_name';
            $score -= 10;
        }

        return [$flags, max(0, $score)];
    }
}
