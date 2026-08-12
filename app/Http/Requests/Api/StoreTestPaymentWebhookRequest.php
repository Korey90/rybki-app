<?php

namespace App\Http\Requests\Api;

use App\Modules\Payments\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestPaymentWebhookRequest extends FormRequest
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
            'provider_event_id' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:255'],
            'provider_payment_id' => ['required', 'string', 'max:255'],
            'status' => [
                'required',
                'string',
                Rule::in([
                    PaymentStatus::Succeeded->value,
                    PaymentStatus::Failed->value,
                ]),
            ],
        ];
    }
}
