<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreCheckoutRequest;
use App\Models\User;
use App\Modules\Bookings\Actions\CreateBookingHold;
use App\Modules\Bookings\Actions\ResolveBookingCancellationQuote;
use App\Modules\Bookings\DTO\BookingCancellationQuote;
use App\Modules\Bookings\Exceptions\BookingHoldUnavailable;
use App\Modules\Bookings\Models\Booking;
use App\Modules\Bookings\Models\BookingPriceLine;
use App\Modules\Bookings\Models\SpotReservation;
use App\Modules\Fisheries\Enums\FishingVenueCommercialStatus;
use App\Modules\Fisheries\Enums\FishingVenueStatus;
use App\Modules\Fisheries\Models\FishingSpot;
use App\Modules\Fisheries\Models\FishingVenue;
use App\Modules\Legal\Actions\RecordLegalAcceptance;
use App\Modules\Legal\Enums\LegalDocumentType;
use App\Modules\Legal\Models\LegalAcceptance;
use App\Modules\Payments\Actions\CreateBookingPayment;
use App\Modules\Payments\Actions\HandlePaymentWebhook;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\PaymentConfirmationFailed;
use App\Modules\Payments\Exceptions\PaymentProviderRequestFailed;
use App\Modules\Payments\Exceptions\PaymentSessionUnavailable;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentGatewayManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(private readonly ResolveBookingCancellationQuote $resolveBookingCancellationQuote) {}

    /**
     * Create a booking hold and a test payment session.
     */
    public function store(
        StoreCheckoutRequest $request,
        CreateBookingHold $createBookingHold,
        CreateBookingPayment $createBookingPayment,
        RecordLegalAcceptance $recordLegalAcceptance
    ): RedirectResponse {
        $validated = $request->validated();
        $spot = FishingSpot::query()
            ->where('uuid', $validated['fishing_spot_uuid'])
            ->whereHas('venue', fn ($query) => $query
                ->where('status', FishingVenueStatus::Published->value)
                ->where('commercial_status', FishingVenueCommercialStatus::Bookable->value))
            ->firstOrFail();

        try {
            $booking = $createBookingHold->handle(
                spot: $spot,
                startsAt: CarbonImmutable::parse((string) $validated['starts_at']),
                endsAt: CarbonImmutable::parse((string) $validated['ends_at']),
                customer: $validated,
                user: $request->user(),
            );

            $paymentSession = $createBookingPayment->handle(
                booking: $booking,
            );

            $user = $request->user();
            $user = $user instanceof User ? $user : null;

            foreach ([
                LegalDocumentType::Terms,
                LegalDocumentType::PrivacyPolicy,
                LegalDocumentType::CancellationRules,
            ] as $documentType) {
                $recordLegalAcceptance->handle(
                    $documentType,
                    'checkout',
                    user: $user,
                    booking: $booking,
                    customerEmail: (string) $validated['customer_email'],
                    request: $request,
                );
            }
        } catch (BookingHoldUnavailable $exception) {
            return back()
                ->withErrors(['fishing_spot_uuid' => $this->availabilityMessage($exception->reason)])
                ->withInput();
        } catch (PaymentSessionUnavailable $exception) {
            return back()
                ->withErrors(['payment' => $this->paymentMessage($exception->reason)])
                ->withInput();
        }

        Inertia::flash('payment_session', $paymentSession->toArray());

        return to_route('checkout.show', $booking);
    }

    /**
     * Show a checkout page for a booking.
     */
    public function show(Booking $booking, PaymentGatewayManager $paymentGatewayManager): Response
    {
        $booking->load(['venue', 'spotReservations.spot', 'priceLines', 'payments', 'legalAcceptances']);

        return Inertia::render('marketplace/checkout/show', [
            'booking' => $this->bookingPayload($booking),
            'payment_session' => $this->paymentSessionPayload($booking, $paymentGatewayManager),
        ]);
    }

    /**
     * Confirm a local test payment.
     */
    public function confirmTestPayment(
        Request $request,
        Booking $booking,
        PaymentGatewayManager $paymentGatewayManager,
        HandlePaymentWebhook $handlePaymentWebhook
    ): RedirectResponse {
        $booking->load('payments');

        $payment = $booking->payments()
            ->whereIn('status', [
                PaymentStatus::Created->value,
                PaymentStatus::Processing->value,
            ])
            ->where('provider', PaymentProvider::Test->value)
            ->latest()
            ->first();

        if (! $payment instanceof Payment || $payment->provider_payment_id === null) {
            return back()->withErrors(['payment' => 'Nie znaleziono aktywnej płatności testowej.']);
        }

        try {
            $handlePaymentWebhook->handle(
                gateway: $paymentGatewayManager->for(PaymentProvider::Test->value),
                payload: [
                    'provider_event_id' => 'test_evt_'.Str::uuid(),
                    'event_type' => 'payment.succeeded',
                    'provider_payment_id' => $payment->provider_payment_id,
                    'status' => PaymentStatus::Succeeded->value,
                ],
            );
        } catch (PaymentConfirmationFailed $exception) {
            return back()->withErrors(['payment' => $this->paymentMessage($exception->reason)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking confirmed.')]);

        $user = $request->user();
        if ($user instanceof User && $booking->user_id === $user->id) {
            return to_route('account.bookings.show', $booking);
        }

        return to_route('checkout.show', $booking);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(Booking $booking): array
    {
        $venue = $booking->venue;
        assert($venue instanceof FishingVenue);

        return [
            'uuid' => $booking->uuid,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'currency' => $booking->currency,
            'total_amount' => $booking->total_amount,
            'platform_fee_amount' => $booking->platform_fee_amount,
            'operator_amount' => $booking->operator_amount,
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            'venue' => [
                'uuid' => $venue->uuid,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
            ],
            'reservations' => $booking->spotReservations->map(fn (SpotReservation $reservation): array => [
                'uuid' => $reservation->uuid,
                'status' => $reservation->status->value,
                'starts_at' => $reservation->starts_at->toIso8601String(),
                'ends_at' => $reservation->ends_at->toIso8601String(),
                'hold_expires_at' => $reservation->hold_expires_at?->toIso8601String(),
                'spot' => [
                    'uuid' => $reservation->spot->uuid,
                    'name' => $reservation->spot->name,
                ],
            ])->values()->all(),
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
            ])->values()->all(),
            'legal_acceptances' => $booking->legalAcceptances->map(fn (LegalAcceptance $acceptance): array => [
                'document_type' => $acceptance->document_type->value,
                'document_version' => $acceptance->document_version,
                'context' => $acceptance->context,
                'accepted_at' => $acceptance->accepted_at->toIso8601String(),
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

    /**
     * @return array<string, int|string>|null
     */
    private function paymentSessionPayload(Booking $booking, PaymentGatewayManager $paymentGatewayManager): ?array
    {
        $payment = $booking->payments
            ->whereIn('status', [
                PaymentStatus::Created,
                PaymentStatus::Processing,
            ])
            ->sortByDesc('id')
            ->first();

        if (! $payment instanceof Payment) {
            return null;
        }

        if ($payment->provider_payment_id !== null && $payment->checkout_url !== null) {
            return [
                'provider' => $payment->provider->value,
                'provider_payment_id' => $payment->provider_payment_id,
                'checkout_url' => $payment->checkout_url,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ];
        }

        try {
            return $paymentGatewayManager
                ->for($payment->provider->value)
                ->createPayment($booking, $payment)
                ->toArray();
        } catch (PaymentProviderRequestFailed|PaymentSessionUnavailable) {
            return null;
        }
    }

    private function availabilityMessage(string $reason): string
    {
        return match ($reason) {
            'reserved' => 'Wybrane stanowisko jest już zajęte w tym terminie.',
            'blocked' => 'Wybrane stanowisko jest zablokowane w tym terminie.',
            'outside_availability_rules' => 'Termin jest poza dostępnością stanowiska.',
            'venue_not_bookable' => 'To łowisko nie przyjmuje rezerwacji online.',
            default => 'Nie można utworzyć rezerwacji dla tego terminu.',
        };
    }

    private function paymentMessage(string $reason): string
    {
        return match ($reason) {
            'hold_expired' => 'Czas na płatność minął. Wybierz termin ponownie.',
            'active_hold_not_found' => 'Nie znaleziono aktywnej blokady stanowiska.',
            'booking_not_payment_pending' => 'Ta rezerwacja nie oczekuje na płatność.',
            'spot_already_confirmed' => 'Stanowisko zostało już potwierdzone dla innej rezerwacji.',
            'provider_account_not_ready' => 'Operator łowiska nie ma jeszcze gotowego konta u aktywnego providera płatności.',
            'stripe_not_configured', 'payu_not_configured', 'provider_not_configured' => 'Aktywny provider płatności nie jest jeszcze skonfigurowany.',
            default => 'Nie udało się obsłużyć płatności.',
        };
    }
}
