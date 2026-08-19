<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Legal\Models\LegalAcceptance;
use App\Modules\Reviews\Models\FishingVenueReview;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $user->load([
            'organizationMemberships.organization',
            'bookings.venue',
            'bookings.spotReservations.spot',
            'bookings.payments',
            'favoriteFishingVenues',
            'fishingVenueReviews.venue',
            'legalAcceptances',
        ]);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'user' => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'account_type' => $user->account_type->value,
                'marketing_consent_at' => $user->marketing_consent_at?->toIso8601String(),
                'marketing_consent_document_version' => $user->marketing_consent_document_version,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'organizations' => $user->organizationMemberships->map(fn ($membership): array => [
                'organization_uuid' => $membership->organization->uuid,
                'organization_name' => $membership->organization->name,
                'role' => $membership->role->value,
            ])->values()->all(),
            'bookings' => $user->bookings->map(fn (Booking $booking): array => [
                'uuid' => $booking->uuid,
                'booking_number' => $booking->booking_number,
                'status' => $booking->status->value,
                'venue' => $booking->venue->name,
                'customer_name' => $booking->customer_name,
                'customer_email' => $booking->customer_email,
                'total_amount' => $booking->total_amount,
                'currency' => $booking->currency,
                'created_at' => $booking->created_at?->toIso8601String(),
                'reservations' => $booking->spotReservations->map(fn ($reservation): array => [
                    'spot' => $reservation->spot->name,
                    'starts_at' => $reservation->starts_at->toIso8601String(),
                    'ends_at' => $reservation->ends_at->toIso8601String(),
                    'status' => $reservation->status->value,
                ])->values()->all(),
                'payments' => $booking->payments->map(fn ($payment): array => [
                    'provider' => $payment->provider->value,
                    'status' => $payment->status->value,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ])->values()->all(),
            ])->values()->all(),
            'favorite_venues' => $user->favoriteFishingVenues->map(fn ($venue): array => [
                'uuid' => $venue->uuid,
                'name' => $venue->name,
                'slug' => $venue->slug,
            ])->values()->all(),
            'reviews' => $user->fishingVenueReviews->map(fn (FishingVenueReview $review): array => [
                'uuid' => $review->uuid,
                'venue' => $review->venue->name,
                'rating' => $review->rating,
                'title' => $review->title,
                'body' => $review->body,
                'status' => $review->status->value,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values()->all(),
            'legal_acceptances' => $user->legalAcceptances->map(fn (LegalAcceptance $acceptance): array => [
                'document_type' => $acceptance->document_type->value,
                'document_version' => $acceptance->document_version,
                'context' => $acceptance->context,
                'marketing_consent' => $acceptance->marketing_consent,
                'accepted_at' => $acceptance->accepted_at->toIso8601String(),
            ])->values()->all(),
        ];

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, 'rybki-data-export.json', [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }
}
