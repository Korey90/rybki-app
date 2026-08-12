<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvailabilityRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'fishing_spot_id' => ['required', 'integer', 'exists:fishing_spots,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'minimum_booking_minutes' => ['required', 'integer', 'min:1'],
            'maximum_booking_minutes' => ['nullable', 'integer', 'gte:minimum_booking_minutes'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'valid_from' => $this->input('valid_from') ?: null,
            'valid_until' => $this->input('valid_until') ?: null,
            'maximum_booking_minutes' => $this->input('maximum_booking_minutes') ?: null,
        ]);
    }
}
