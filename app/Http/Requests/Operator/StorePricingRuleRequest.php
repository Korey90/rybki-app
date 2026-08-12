<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class StorePricingRuleRequest extends FormRequest
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
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'day_of_week' => ['nullable', 'integer', 'between:1,7'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'minimum_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'starts_at' => $this->input('starts_at') ?: null,
            'ends_at' => $this->input('ends_at') ?: null,
            'day_of_week' => $this->input('day_of_week') ?: null,
            'currency' => strtoupper((string) ($this->input('currency') ?: 'PLN')),
            'minimum_duration_minutes' => $this->input('minimum_duration_minutes') ?: null,
            'priority' => $this->input('priority') ?: 0,
        ]);
    }
}
