<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Actions\RecordAuditLog;
use App\Modules\Reviews\Actions\ModerateFishingVenueReview;
use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
use App\Modules\Reviews\Models\FishingVenueReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FishingVenueReviewController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('moderate', FishingVenueReview::class);

        $reviews = FishingVenueReview::query()
            ->with(['venue.organization', 'user', 'booking', 'moderator', 'operatorResponder'])
            ->latest()
            ->limit(150)
            ->get();

        return Inertia::render('admin/reviews/index', [
            'reviews' => $reviews->map(fn (FishingVenueReview $review): array => $this->reviewPayload($review))->values()->all(),
        ]);
    }

    public function moderate(
        Request $request,
        FishingVenueReview $fishingVenueReview,
        ModerateFishingVenueReview $moderateFishingVenueReview,
        RecordAuditLog $recordAuditLog
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);
        Gate::authorize('moderate', FishingVenueReview::class);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::enum(FishingVenueReviewStatus::class)],
            'hidden_reason' => ['nullable', 'required_if:status,'.FishingVenueReviewStatus::Hidden->value, 'string', 'max:500'],
        ]);

        $fishingVenueReview->loadMissing('venue.organization');
        $before = [
            'status' => $fishingVenueReview->status->value,
            'hidden_reason' => $fishingVenueReview->hidden_reason,
        ];

        $review = $moderateFishingVenueReview->handle(
            review: $fishingVenueReview,
            moderator: $user,
            status: FishingVenueReviewStatus::from((string) $validated['status']),
            hiddenReason: $validated['hidden_reason'] ?? null,
        );

        $recordAuditLog->handle(
            action: 'fishing_venue_review.moderated',
            subject: $review,
            actor: $user,
            organization: $review->venue->organization,
            before: $before,
            after: [
                'status' => $review->status->value,
                'hidden_reason' => $review->hidden_reason,
            ],
            request: $request,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Status opinii został zapisany.',
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
            'moderated_at' => $review->moderated_at?->toIso8601String(),
            'venue' => [
                'name' => $review->venue->name,
                'slug' => $review->venue->slug,
                'organization' => $review->venue->organization?->name,
            ],
            'booking' => [
                'booking_number' => $review->booking->booking_number,
                'uuid' => $review->booking->uuid,
            ],
            'author' => [
                'name' => $review->user->name,
                'email' => $review->user->email,
            ],
            'moderator' => $review->moderator === null ? null : [
                'name' => $review->moderator->name,
                'email' => $review->moderator->email,
            ],
            'operator_responder' => $review->operatorResponder === null ? null : [
                'name' => $review->operatorResponder->name,
                'email' => $review->operatorResponder->email,
            ],
        ];
    }
}
