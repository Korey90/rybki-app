<?php

namespace App\Modules\GovernmentData\Actions;

use App\Modules\Fisheries\Models\FishingVenue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FindGovernmentVenueMatches
{
    /**
     * @param  array<string, mixed>  $normalized
     * @return list<array<string, mixed>>
     */
    public function handle(array $normalized): array
    {
        $name = (string) ($normalized['name'] ?? '');
        $city = $this->nullableString($normalized['city'] ?? null);
        $voivodeship = $this->nullableString($normalized['voivodeship'] ?? null);

        /** @var Collection<int, FishingVenue> $venues */
        $venues = FishingVenue::query()
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        return array_values($venues
            ->map(fn (FishingVenue $venue): array => $this->candidatePayload($venue, $name, $city, $voivodeship))
            ->filter(fn (array $candidate): bool => (int) $candidate['score'] >= 55)
            ->sortByDesc('score')
            ->take(5)
            ->values()
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function candidatePayload(FishingVenue $venue, string $name, ?string $city, ?string $voivodeship): array
    {
        similar_text($this->normalize($name), $this->normalize($venue->name), $nameSimilarity);

        $score = (int) round($nameSimilarity * 0.75);
        $reasons = [
            'name_similarity' => (int) round($nameSimilarity),
        ];

        if ($city !== null && $this->same($city, $venue->city)) {
            $score += 15;
            $reasons['city'] = true;
        }

        if ($voivodeship !== null && $this->same($voivodeship, $venue->voivodeship)) {
            $score += 10;
            $reasons['voivodeship'] = true;
        }

        return [
            'venue_uuid' => $venue->uuid,
            'venue_id' => $venue->id,
            'name' => $venue->name,
            'city' => $venue->city,
            'voivodeship' => $venue->voivodeship,
            'commercial_status' => $venue->commercial_status->value,
            'organization_id' => $venue->organization_id,
            'score' => min(100, $score),
            'reasons' => $reasons,
        ];
    }

    private function same(?string $left, ?string $right): bool
    {
        if ($left === null || $right === null) {
            return false;
        }

        return $this->normalize($left) === $this->normalize($right);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($value))) ?? '');
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
