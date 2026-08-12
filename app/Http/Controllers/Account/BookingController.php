<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Bookings\Actions\CancelBooking;
use App\Modules\Bookings\Actions\ResolveBookingCancellationQuote;
use App\Modules\Bookings\DTO\BookingCancellationQuote;
use App\Modules\Bookings\Exceptions\BookingCancellationUnavailable;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\BookingPriceLine;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Payments\Exceptions\RefundFailed;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\Refund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(private readonly ResolveBookingCancellationQuote $resolveBookingCancellationQuote) {}

    /**
     * Show bookings for the authenticated angler.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        $bookings = Booking::query()
            ->with(['venue', 'spotReservations.spot', 'payments' => fn ($query) => $query->latest()])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return Inertia::render('account/bookings/index', [
            'bookings' => $bookings->map(fn (Booking $booking): array => $this->bookingListPayload($booking))->all(),
        ]);
    }

    /**
     * Show a single booking for the authenticated angler.
     */
    public function show(Request $request, Booking $booking): Response
    {
        $user = $request->user();
        assert($user instanceof User);

        Gate::authorize('viewAsCustomer', $booking);

        $booking->load(['venue', 'spotReservations.spot', 'priceLines', 'payments', 'refunds']);

        return Inertia::render('account/bookings/show', [
            'booking' => $this->bookingDetailPayload($booking),
        ]);
    }

    /**
     * Cancel a booking owned by the authenticated angler.
     */
    public function cancel(Request $request, Booking $booking, CancelBooking $cancelBooking): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        Gate::authorize('cancelAsCustomer', $booking);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $cancelBooking->handle(
                booking: $booking,
                actor: $user,
                actorType: CancelBooking::ACTOR_ANGLER,
                reason: $validated['reason'] ?? null,
            );
        } catch (BookingCancellationUnavailable $exception) {
            return back()->withErrors([
                'cancellation' => $this->cancellationMessage($exception->reason),
            ]);
        } catch (RefundFailed $exception) {
            return back()->withErrors([
                'cancellation' => $this->refundMessage($exception->reason),
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Rezerwacja została anulowana.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingListPayload(Booking $booking): array
    {
        $venue = $booking->venue;
        assert($venue instanceof FishingVenue);

        $reservation = $booking->spotReservations->first();
        $payment = $booking->payments->first();

        return [
            'uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'total_amount' => $booking->total_amount,
            'currency' => $booking->currency,
            'created_at' => $booking->created_at?->toIso8601String(),
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'venue' => [
                'uuid' => $venue->uuid,
                'slug' => $venue->slug,
                'name' => $venue->name,
                'city' => $venue->city,
            ],
            'reservation' => $reservation instanceof SpotReservation ? [
                'uuid' => $reservation->uuid,
                'status' => $reservation->status->value,
                'starts_at' => $reservation->starts_at->toIso8601String(),
                'ends_at' => $reservation->ends_at->toIso8601String(),
                'spot_name' => $reservation->spot->name,
            ] : null,
            'latest_payment' => $payment instanceof Payment ? [
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingDetailPayload(Booking $booking): array
    {
        return [
            ...$this->bookingListPayload($booking),
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'price_lines' => $booking->priceLines->map(fn (BookingPriceLine $line): array => [
                'type' => $line->type->value,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_amount' => $line->unit_amount,
                'total_amount' => $line->total_amount,
            ])->values()->all(),
            'payments' => $booking->payments->map(fn (Payment $payment): array => [
                'uuid' => $payment->uuid,
                'provider' => $payment->provider->value,
                'provider_payment_id' => $payment->provider_payment_id,
                'status' => $payment->status->value,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'failed_at' => $payment->failed_at?->toIso8601String(),
                'created_at' => $payment->created_at?->toIso8601String(),
            ])->values()->all(),
            'refunds' => $booking->refunds->map(fn (Refund $refund): array => [
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'reason' => $refund->reason,
                'status' => $refund->status->value,
                'created_at' => $refund->created_at?->toIso8601String(),
            ])->values()->all(),
            'cancellation_policy' => $this->cancellationPolicyPayload($booking),
            'cancellation' => $this->cancellationQuotePayload($this->resolveBookingCancellationQuote->handle($booking)),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function cancellationPolicyPayload(Booking $booking): array
    {
        $snapshot = $booking->cancellation_policy_snapshot ?? [];

        return [
            'name' => (string) ($snapshot['name'] ?? 'Standard MVP'),
            'free_cancellation_hours' => (int) ($snapshot['free_cancellation_hours'] ?? 24),
            'late_refund_percentage' => (int) ($snapshot['late_refund_percentage'] ?? 0),
        ];
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    private function cancellationQuotePayload(BookingCancellationQuote $quote): array
    {
        return [
            'can_cancel' => $quote->canCancel,
            'reason' => $quote->reason,
            'refund_amount' => $quote->refundAmount,
            'refund_percentage' => $quote->refundPercentage,
            'currency' => $quote->currency,
            'free_cancellation_until' => $quote->freeCancellationUntil?->toIso8601String(),
        ];
    }

    private function cancellationMessage(string $reason): string
    {
        return match ($reason) {
            'already_cancelled' => 'Ta rezerwacja jest już anulowana.',
            'after_start' => 'Nie można anulować rezerwacji po rozpoczęciu terminu.',
            'payment_failed' => 'Rezerwacja z nieudaną płatnością nie wymaga anulowania.',
            'missing_reservation' => 'Nie znaleziono terminu rezerwacji.',
            default => 'Nie można anulować tej rezerwacji.',
        };
    }

    private function refundMessage(string $reason): string
    {
        return match ($reason) {
            'provider_refund_failed' => 'Provider płatności odrzucił zwrot.',
            default => 'Nie udało się wykonać zwrotu u providera płatności.',
        };
    }
}
