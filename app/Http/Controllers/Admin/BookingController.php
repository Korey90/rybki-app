<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Payments\Models\Payment;
use App\Support\CsvStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $this->validatedFilters($request);
        $bookings = $this->filteredQuery($filters)
            ->with(['organization', 'venue', 'user', 'spotReservations.spot', 'payments' => fn ($query) => $query->latest()])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('admin/bookings/index', [
            'bookings' => $bookings->map(fn (Booking $booking): array => $this->bookingPayload($booking))->values()->all(),
            'filters' => $filters,
            'statuses' => array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::cases()),
            'export_url' => route('admin.bookings.export', array_filter($filters, fn ($value): bool => $value !== null && $value !== '')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $bookings = $this->filteredQuery($filters)
            ->with(['organization', 'venue'])
            ->latest()
            ->limit(5000)
            ->get();

        return CsvStream::download('admin-bookings.csv', [
            'uuid',
            'booking_number',
            'status',
            'organization',
            'venue',
            'customer_name',
            'customer_email',
            'total_amount',
            'currency',
            'confirmed_at',
            'created_at',
        ], $bookings->map(fn (Booking $booking): array => [
            $booking->uuid,
            $booking->booking_number,
            $booking->status->value,
            $booking->organization->name,
            $booking->venue->name,
            $booking->customer_name,
            $booking->customer_email,
            (string) $booking->total_amount,
            $booking->currency,
            $booking->confirmed_at?->toIso8601String(),
            $booking->created_at?->toIso8601String(),
        ]));
    }

    /**
     * @return array{q: string|null, status: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
        ]);

        $status = $validated['status'] ?? null;
        if (! in_array($status, array_map(fn (BookingStatus $status): string => $status->value, BookingStatus::cases()), true)) {
            $status = null;
        }

        return [
            'q' => $validated['q'] ?? null,
            'status' => $status,
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<Booking>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Booking::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('booking_number', 'like', "%{$search}%")
                        ->orWhere('uuid', $search)
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhereHas('venue', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('organization', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(Booking $booking): array
    {
        $reservation = $booking->spotReservations->first();
        $payment = $booking->payments->first();

        return [
            'uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'total_amount' => $booking->total_amount,
            'operator_amount' => $booking->operator_amount,
            'platform_fee_amount' => $booking->platform_fee_amount,
            'currency' => $booking->currency,
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'created_at' => $booking->created_at?->toIso8601String(),
            'organization' => [
                'uuid' => $booking->organization->uuid,
                'name' => $booking->organization->name,
            ],
            'venue' => [
                'uuid' => $booking->venue->uuid,
                'name' => $booking->venue->name,
                'slug' => $booking->venue->slug,
            ],
            'user' => $booking->user === null ? null : [
                'uuid' => $booking->user->uuid,
                'name' => $booking->user->name,
                'email' => $booking->user->email,
            ],
            'reservation' => $reservation instanceof SpotReservation ? $this->reservationPayload($reservation) : null,
            'latest_payment' => $payment instanceof Payment ? [
                'uuid' => $payment->uuid,
                'provider' => $payment->provider->value,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationPayload(SpotReservation $reservation): array
    {
        $spot = $reservation->spot;
        assert($spot instanceof FishingSpot);

        return [
            'uuid' => $reservation->uuid,
            'status' => $reservation->status->value,
            'starts_at' => $reservation->starts_at->toIso8601String(),
            'ends_at' => $reservation->ends_at->toIso8601String(),
            'spot' => [
                'uuid' => $spot->uuid,
                'name' => $spot->name,
            ],
        ];
    }
}
