<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Organizations\Enums\OrganizationVerificationStatus;
use App\Modules\Organizations\Models\Organization;
use App\Support\CsvStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $this->validatedFilters($request);
        $organizations = $this->filteredQuery($filters)
            ->withCount(['users as members_count', 'fishingVenues as venues_count', 'bookings as bookings_count', 'providerAccounts as provider_accounts_count'])
            ->orderBy('name')
            ->limit(100)
            ->get();

        return Inertia::render('admin/organizations/index', [
            'organizations' => $organizations->map(fn (Organization $organization): array => $this->organizationPayload($organization))->values()->all(),
            'filters' => $filters,
            'verification_statuses' => array_map(fn (OrganizationVerificationStatus $status): string => $status->value, OrganizationVerificationStatus::cases()),
            'export_url' => route('admin.organizations.export', array_filter($filters, fn ($value): bool => $value !== null && $value !== '')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $organizations = $this->filteredQuery($filters)
            ->withCount(['users as members_count', 'fishingVenues as venues_count', 'bookings as bookings_count'])
            ->orderBy('name')
            ->limit(5000)
            ->get();

        return CsvStream::download('admin-organizations.csv', [
            'uuid',
            'name',
            'legal_name',
            'tax_id',
            'verification_status',
            'members_count',
            'venues_count',
            'bookings_count',
            'created_at',
        ], $organizations->map(fn (Organization $organization): array => [
            $organization->uuid,
            $organization->name,
            $organization->legal_name,
            $organization->tax_id,
            $organization->verification_status->value,
            (string) ($organization->getAttribute('members_count') ?? 0),
            (string) ($organization->getAttribute('venues_count') ?? 0),
            (string) ($organization->getAttribute('bookings_count') ?? 0),
            $organization->created_at?->toIso8601String(),
        ]));
    }

    /**
     * @return array{q: string|null, verification_status: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'verification_status' => ['nullable', 'string'],
        ]);

        $status = $validated['verification_status'] ?? null;
        if (! in_array($status, array_map(fn (OrganizationVerificationStatus $status): string => $status->value, OrganizationVerificationStatus::cases()), true)) {
            $status = null;
        }

        return [
            'q' => $validated['q'] ?? null,
            'verification_status' => $status,
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<Organization>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Organization::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%")
                        ->orWhere('regon', 'like', "%{$search}%")
                        ->orWhere('uuid', $search);
                });
            })
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status));
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationPayload(Organization $organization): array
    {
        return [
            'uuid' => $organization->uuid,
            'name' => $organization->name,
            'legal_name' => $organization->legal_name,
            'tax_id' => $organization->tax_id,
            'regon' => $organization->regon,
            'country' => $organization->country,
            'verification_status' => $organization->verification_status->value,
            'members_count' => (int) ($organization->getAttribute('members_count') ?? 0),
            'venues_count' => (int) ($organization->getAttribute('venues_count') ?? 0),
            'bookings_count' => (int) ($organization->getAttribute('bookings_count') ?? 0),
            'provider_accounts_count' => (int) ($organization->getAttribute('provider_accounts_count') ?? 0),
            'created_at' => $organization->created_at?->toIso8601String(),
        ];
    }
}
