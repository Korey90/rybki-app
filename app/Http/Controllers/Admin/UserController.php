<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserAccountType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CsvStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $this->validatedFilters($request);
        $query = $this->filteredQuery($filters)
            ->withCount([
                'bookings',
                'favoriteFishingVenues as favorites_count',
                'fishingVenueReviews as reviews_count',
                'organizationMemberships as memberships_count',
            ])
            ->latest()
            ->limit(100);

        $users = $query->get();

        return Inertia::render('admin/users/index', [
            'users' => $users->map(fn (User $user): array => $this->userPayload($user))->values()->all(),
            'filters' => $filters,
            'account_types' => array_map(fn (UserAccountType $type): string => $type->value, UserAccountType::cases()),
            'export_url' => route('admin.users.export', array_filter($filters, fn ($value): bool => $value !== null && $value !== '')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $users = $this->filteredQuery($filters)
            ->withCount(['bookings', 'organizationMemberships as memberships_count'])
            ->latest()
            ->limit(5000)
            ->get();

        return CsvStream::download('admin-users.csv', [
            'uuid',
            'name',
            'email',
            'phone',
            'account_type',
            'is_admin',
            'bookings_count',
            'memberships_count',
            'created_at',
        ], $users->map(fn (User $user): array => [
            $user->uuid,
            $user->name,
            $user->email,
            $user->phone,
            $user->account_type->value,
            $user->is_admin ? '1' : '0',
            (string) ($user->getAttribute('bookings_count') ?? 0),
            (string) ($user->getAttribute('memberships_count') ?? 0),
            $user->created_at?->toIso8601String(),
        ]));
    }

    /**
     * @return array{q: string|null, account_type: string|null, is_admin: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'string'],
            'is_admin' => ['nullable', 'string', 'in:0,1'],
        ]);

        $accountType = $validated['account_type'] ?? null;
        if (! in_array($accountType, array_map(fn (UserAccountType $type): string => $type->value, UserAccountType::cases()), true)) {
            $accountType = null;
        }

        return [
            'q' => $validated['q'] ?? null,
            'account_type' => $accountType,
            'is_admin' => $validated['is_admin'] ?? null,
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<User>
     */
    private function filteredQuery(array $filters): Builder
    {
        return User::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('uuid', $search);
                });
            })
            ->when($filters['account_type'] ?? null, fn (Builder $query, string $type) => $query->where('account_type', $type))
            ->when($filters['is_admin'] !== null, fn (Builder $query) => $query->where('is_admin', $filters['is_admin'] === '1'));
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'account_type' => $user->account_type->value,
            'is_admin' => $user->is_admin,
            'bookings_count' => (int) ($user->getAttribute('bookings_count') ?? 0),
            'favorites_count' => (int) ($user->getAttribute('favorites_count') ?? 0),
            'reviews_count' => (int) ($user->getAttribute('reviews_count') ?? 0),
            'memberships_count' => (int) ($user->getAttribute('memberships_count') ?? 0),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
