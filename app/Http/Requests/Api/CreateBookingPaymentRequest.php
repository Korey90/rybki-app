<?php

namespace App\Http\Requests\Api;

use App\Modules\Payments\Enums\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBookingPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'provider' => ['nullable', 'string', Rule::enum(PaymentProvider::class)],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->input('provider') ?: null,
            'idempotency_key' => $this->input('idempotency_key') ?: null,
        ]);
    }
}
