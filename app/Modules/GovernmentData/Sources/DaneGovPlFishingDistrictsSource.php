<?php

namespace App\Modules\GovernmentData\Sources;

use App\Modules\Fisheries\Enums\FishingVenueType;
use App\Modules\GovernmentData\Models\DataSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DaneGovPlFishingDistrictsSource
{
    public const PROVIDER = 'wody_polskie';

    public const DATASET_ID = '2914';

    public const DATASET_NAME = 'Ujednolicony wykaz obwodów rybackich';

    public const DATASET_URL = 'https://dane.gov.pl/pl/dataset/2914,ujednolicony-wykaz-obwodow-rybackich';

    /**
     * @return array{
     *     data_source:DataSource,
     *     resource:array<string, mixed>,
     *     records:list<array<string, mixed>>
     * }
     */
    public function fetch(?int $limit = null): array
    {
        $dataSource = $this->dataSource();
        $resources = $this->resources();
        $resource = $this->latestCsvResource($resources);
        $csvUrl = $this->csvUrl($resource);

        if ($csvUrl === null) {
            throw new RuntimeException('Najnowszy zasób dane.gov.pl nie ma linku CSV.');
        }

        $csv = Http::timeout(60)
            ->accept('text/csv')
            ->get($csvUrl)
            ->throw()
            ->body();

        return [
            'data_source' => $dataSource,
            'resource' => [
                'id' => (string) ($resource['id'] ?? ''),
                'title' => (string) Arr::get($resource, 'attributes.title', ''),
                'url' => $csvUrl,
                'data_date' => Arr::get($resource, 'attributes.data_date'),
                'modified' => Arr::get($resource, 'attributes.modified'),
            ],
            'records' => $this->parseCsv($csv, $resource, $limit),
        ];
    }

    public function dataSource(): DataSource
    {
        /** @var DataSource $dataSource */
        $dataSource = DataSource::query()->updateOrCreate(
            [
                'provider' => self::PROVIDER,
                'dataset_name' => self::DATASET_NAME,
            ],
            [
                'dataset_url' => self::DATASET_URL,
                'external_dataset_id' => self::DATASET_ID,
                'default_format' => 'csv',
                'sync_enabled' => true,
                'metadata' => [
                    'api_base_url' => $this->baseUrl(),
                    'resources_url' => $this->resourcesUrl(),
                    'source_kind' => 'dane.gov.pl',
                ],
            ],
        );

        return $dataSource;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resources(): array
    {
        $payload = Http::timeout(30)
            ->acceptJson()
            ->get($this->resourcesUrl())
            ->throw()
            ->json();

        $data = Arr::get(is_array($payload) ? $payload : [], 'data');

        if (! is_array($data)) {
            throw new RuntimeException('Nieprawidłowa odpowiedź API dane.gov.pl dla listy zasobów.');
        }

        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $resources
     * @return array<string, mixed>
     */
    private function latestCsvResource(array $resources): array
    {
        $csvResources = collect($resources)
            ->filter(fn (array $resource): bool => $this->csvUrl($resource) !== null)
            ->sortByDesc(fn (array $resource): string => (string) (Arr::get($resource, 'attributes.data_date') ?: Arr::get($resource, 'attributes.modified') ?: ''))
            ->values();

        $resource = $csvResources->first();

        if (! is_array($resource)) {
            throw new RuntimeException('Dataset dane.gov.pl nie zawiera zasobu CSV.');
        }

        return $resource;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function csvUrl(array $resource): ?string
    {
        $csvDownloadUrl = Arr::get($resource, 'attributes.csv_download_url');

        if (is_string($csvDownloadUrl) && $csvDownloadUrl !== '') {
            return $csvDownloadUrl;
        }

        $files = Arr::get($resource, 'attributes.files', []);

        if (is_array($files)) {
            foreach ($files as $file) {
                if (! is_array($file)) {
                    continue;
                }

                if (Str::lower((string) ($file['format'] ?? '')) === 'csv'
                    && is_string($file['download_url'] ?? null)
                    && $file['download_url'] !== '') {
                    return $file['download_url'];
                }
            }
        }

        if (Str::lower((string) Arr::get($resource, 'attributes.format')) === 'csv') {
            $downloadUrl = Arr::get($resource, 'attributes.download_url');

            return is_string($downloadUrl) && $downloadUrl !== '' ? $downloadUrl : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return list<array<string, mixed>>
     */
    private function parseCsv(string $csv, array $resource, ?int $limit): array
    {
        $handle = fopen('php://temp', 'r+');
        assert(is_resource($handle));
        fwrite($handle, $csv);
        rewind($handle);

        $headers = fgetcsv($handle, 0, ',');

        if (! is_array($headers)) {
            fclose($handle);

            throw new RuntimeException('CSV dane.gov.pl nie zawiera nagłówków.');
        }

        $headers = array_map(fn (?string $header): string => $this->cleanHeader((string) $header), $headers);
        $records = [];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count(array_filter($row, fn (mixed $value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $raw = $this->combineRow($headers, $row);
            $records[] = $this->recordFromRow($raw, $resource);

            if ($limit !== null && count($records) >= $limit) {
                break;
            }
        }

        fclose($handle);

        return $records;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $row
     * @return array<string, mixed>
     */
    private function combineRow(array $headers, array $row): array
    {
        $raw = [];

        foreach ($headers as $index => $header) {
            $raw[$header] = $row[$index] ?? null;
        }

        return $raw;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    private function recordFromRow(array $raw, array $resource): array
    {
        $lp = $this->string($raw['Lp'] ?? null) ?? Str::random(8);
        $rzgw = $this->string($raw['RZGW'] ?? null);
        $districtName = $this->string($raw['Nazwa obwodu rybackiego'] ?? null) ?? "Pozycja {$lp}";
        $description = $this->string($raw['Opis obwodu'] ?? null);
        $agreementStart = $this->string($raw['Termin rozpoczęcia umowy'] ?? null);
        $agreementEnd = $this->string($raw['Termin wygaśnięcia umowy'] ?? null);
        $sourceUpdatedAt = $this->string(Arr::get($resource, 'attributes.data_date') ?? Arr::get($resource, 'attributes.modified'));
        $externalHash = substr(sha1(implode('|', [self::DATASET_ID, $rzgw, $districtName])), 0, 16);
        $fullDescription = trim(implode("\n\n", array_filter([
            $rzgw === null ? null : "RZGW: {$rzgw}",
            $description,
            $agreementStart === null && $agreementEnd === null ? null : 'Termin umowy: '.($agreementStart ?? '-').' - '.($agreementEnd ?? '-'),
        ])));

        return [
            'external_id' => "wody-polskie-obwod-{$externalHash}",
            'name' => "Obwód rybacki {$districtName}",
            'description' => $fullDescription === '' ? null : $fullDescription,
            'venue_type' => FishingVenueType::WodyPolskie->value,
            'country' => 'PL',
            'source_updated_at' => $sourceUpdatedAt,
            '_source_payload' => [
                'provider' => self::PROVIDER,
                'dataset_id' => self::DATASET_ID,
                'resource_id' => (string) ($resource['id'] ?? ''),
                'resource_title' => Arr::get($resource, 'attributes.title'),
                'resource_data_date' => Arr::get($resource, 'attributes.data_date'),
                'row' => $raw,
            ],
        ];
    }

    private function cleanHeader(string $header): string
    {
        return trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $header));
    }

    private function string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }

    private function resourcesUrl(): string
    {
        return $this->baseUrl().'/datasets/'.self::DATASET_ID.'/resources';
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.government_data.dane_gov_pl.base_url', 'https://api.dane.gov.pl/1.4'), '/');
    }
}
