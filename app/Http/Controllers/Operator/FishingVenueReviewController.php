<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Organizations\Queries\ManageableOrganizations;
use App\Modules\Reviews\Actions\RespondToFishingVenueReview;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use App\Modules\Reviews\Models\FishingVenueReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FishingVenueReviewController extends Controller
{
    public function __construct(private readonly ManageableOrganizations $manageableOrganizations) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('viewAny', FishingVenueReview::class);

        $validated = $request->validate([
            'status' => ['nullable', 'string'],
        ]);
        $allowedStatuses = array_map(fn (FishingVenueReviewStatus $status): string => $status->value, FishingVenueReviewStatus::cases());
        $status = Arr::get($validated, 'status');

        if (! in_array($status, $allowedStatuses, true)) {
            $status = null;
        }

        $organizationIds = $this->manageableOrganizations->idsFor($user);
        $baseQuery = FishingVenueReview::query()
            ->whereHas('venue', fn ($query) => $query->whereIn('organization_id', $organizationIds));

        $reviews = (clone $baseQuery)
            ->with(['venue', 'user', 'booking'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->limit(100)
            ->get();

        $statusCounts = [];
        foreach (FishingVenueReviewStatus::cases() as $reviewStatus) {
            $statusCounts[$reviewStatus->value] = (clone $baseQuery)
                ->where('status', $reviewStatus->value)
                ->count();
        }

        return Inertia::render('operator/reviews/index', [
            'reviews' => $reviews->map(fn (FishingVenueReview $review): array => $this->reviewPayload($review))->values()->all(),
            'filters' => [
                'status' => $status,
            ],
            'status_counts' => $statusCounts,
        ]);
    }

    public function respond(
        Request $request,
        FishingVenueReview $fishingVenueReview,
        RespondToFishingVenueReview $respondToFishingVenueReview
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $fishingVenueReview->loadMissing('venue');
        Gate::authorize('respond', $fishingVenueReview);

        $validated = $request->validate([
            'operator_response' => ['required', 'string', 'max:2000'],
        ]);

        $respondToFishingVenueReview->handle($fishingVenueReview, $user, (string) $validated['operator_response']);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Odpowiedź operatora została zapisana.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewPayload(FishingVenueReview $review): array
    {
        return [
            'uuid' => $review->uuid,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'status' => $review->status->value,
            'hidden_reason' => $review->hidden_reason,
            'operator_response' => $review->operator_response,
            'operator_responded_at' => $review->operator_responded_at?->toIso8601String(),
            'created_at' => $review->created_at?->toIso8601String(),
            'venue' => [
                'name' => $review->venue->name,
                'slug' => $review->venue->slug,
            ],
            'booking' => [
                'booking_number' => $review->booking->booking_number,
                'uuid' => $review->booking->uuid,
            ],
            'author' => [
                'name' => $review->user->name,
                'email' => $review->user->email,
            ],
        ];
    }
}
