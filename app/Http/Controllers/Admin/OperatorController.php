<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Models\OrganizationMember;
use App\Support\CsvStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperatorController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $this->validatedFilters($request);
        $operators = $this->filteredQuery($filters)
            ->with([
                'organizationMemberships' => fn ($query) => $query
                    ->with('organization')
                    ->whereIn('role', [OrganizationMemberRole::Owner, OrganizationMemberRole::Manager]),
            ])
            ->withCount(['organizationMemberships as managed_organizations_count' => fn ($query) => $query->whereIn('role', [
                OrganizationMemberRole::Owner,
                OrganizationMemberRole::Manager,
            ])])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('admin/operators/index', [
            'operators' => $operators->map(fn (User $user): array => $this->operatorPayload($user))->values()->all(),
            'filters' => $filters,
            'export_url' => route('admin.operators.export', array_filter($filters, fn ($value): bool => $value !== null && $value !== '')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $operators = $this->filteredQuery($filters)
            ->with(['organizationMemberships.organization'])
            ->latest()
            ->limit(5000)
            ->get();

        return CsvStream::download('admin-operators.csv', [
            'uuid',
            'name',
            'email',
            'account_type',
            'organizations',
            'created_at',
        ], $operators->map(fn (User $user): array => [
            $user->uuid,
            $user->name,
            $user->email,
            $user->account_type->value,
            $user->organizationMemberships
                ->filter(fn (OrganizationMember $membership): bool => in_array($membership->role, [
                    OrganizationMemberRole::Owner,
                    OrganizationMemberRole::Manager,
                ], true))
                ->map(fn (OrganizationMember $membership): string => $membership->organization->name.' ('.$membership->role->value.')')
                ->implode('; '),
            $user->created_at?->toIso8601String(),
        ]));
    }

    /**
     * @return array{q: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'q' => $validated['q'] ?? null,
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<User>
     */
    private function filteredQuery(array $filters): Builder
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query
                    ->where('account_type', UserAccountType::VenueOwner->value)
                    ->orWhereHas('organizationMemberships', fn (Builder $query) => $query->whereIn('role', [
                        OrganizationMemberRole::Owner,
                        OrganizationMemberRole::Manager,
                    ]));
            })
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('organizationMemberships.organization', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function operatorPayload(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'account_type' => $user->account_type->value,
            'managed_organizations_count' => (int) ($user->getAttribute('managed_organizations_count') ?? 0),
            'organizations' => $user->organizationMemberships
                ->map(fn (OrganizationMember $membership): array => [
                    'uuid' => $membership->organization->uuid,
                    'name' => $membership->organization->name,
                    'role' => $membership->role->value,
                ])
                ->values()
                ->all(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
