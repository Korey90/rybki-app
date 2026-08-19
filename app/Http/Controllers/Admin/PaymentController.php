<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Support\CsvStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $filters = $this->validatedFilters($request);
        $payments = $this->filteredQuery($filters)
            ->with(['booking.organization', 'booking.venue'])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('admin/payments/index', [
            'payments' => $payments->map(fn (Payment $payment): array => $this->paymentPayload($payment))->values()->all(),
            'filters' => $filters,
            'statuses' => array_map(fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases()),
            'providers' => array_map(fn (PaymentProvider $provider): string => $provider->value, PaymentProvider::cases()),
            'export_url' => route('admin.payments.export', array_filter($filters, fn ($value): bool => $value !== null && $value !== '')),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $payments = $this->filteredQuery($filters)
            ->with(['booking.organization', 'booking.venue'])
            ->latest()
            ->limit(5000)
            ->get();

        return CsvStream::download('admin-payments.csv', [
            'uuid',
            'provider',
            'provider_payment_id',
            'status',
            'amount',
            'currency',
            'booking_number',
            'organization',
            'venue',
            'paid_at',
            'created_at',
        ], $payments->map(fn (Payment $payment): array => [
            $payment->uuid,
            $payment->provider->value,
            $payment->provider_payment_id,
            $payment->status->value,
            (string) $payment->amount,
            $payment->currency,
            $payment->booking->booking_number,
            $payment->booking->organization->name,
            $payment->booking->venue->name,
            $payment->paid_at?->toIso8601String(),
            $payment->created_at?->toIso8601String(),
        ]));
    }

    /**
     * @return array{q: string|null, status: string|null, provider: string|null}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string'],
            'provider' => ['nullable', 'string'],
        ]);

        $status = $validated['status'] ?? null;
        if (! in_array($status, array_map(fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases()), true)) {
            $status = null;
        }

        $provider = $validated['provider'] ?? null;
        if (! in_array($provider, array_map(fn (PaymentProvider $provider): string => $provider->value, PaymentProvider::cases()), true)) {
            $provider = null;
        }

        return [
            'q' => $validated['q'] ?? null,
            'status' => $status,
            'provider' => $provider,
        ];
    }

    /**
     * @param  array<string, string|null>  $filters
     * @return Builder<Payment>
     */
    private function filteredQuery(array $filters): Builder
    {
        return Payment::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('uuid', $search)
                        ->orWhere('provider_payment_id', 'like', "%{$search}%")
                        ->orWhereHas('booking', fn (Builder $query) => $query
                            ->where('booking_number', 'like', "%{$search}%")
                            ->orWhere('customer_email', 'like', "%{$search}%")
                            ->orWhere('customer_name', 'like', "%{$search}%"))
                        ->orWhereHas('booking.venue', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('booking.organization', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['provider'] ?? null, fn (Builder $query, string $provider) => $query->where('provider', $provider));
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(Payment $payment): array
    {
        return [
            'uuid' => $payment->uuid,
            'provider' => $payment->provider->value,
            'provider_payment_id' => $payment->provider_payment_id,
            'checkout_url' => $payment->checkout_url,
            'status' => $payment->status->value,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'failed_at' => $payment->failed_at?->toIso8601String(),
            'created_at' => $payment->created_at?->toIso8601String(),
            'booking' => [
                'uuid' => $payment->booking->uuid,
                'booking_number' => $payment->booking->booking_number,
                'customer_name' => $payment->booking->customer_name,
                'customer_email' => $payment->booking->customer_email,
                'organization' => [
                    'uuid' => $payment->booking->organization->uuid,
                    'name' => $payment->booking->organization->name,
                ],
                'venue' => [
                    'uuid' => $payment->booking->venue->uuid,
                    'name' => $payment->booking->venue->name,
                    'slug' => $payment->booking->venue->slug,
                ],
            ],
        ];
    }
}
