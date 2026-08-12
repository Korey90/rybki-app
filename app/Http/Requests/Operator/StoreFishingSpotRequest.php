<?php

namespace App\Http\Requests\Operator;

use App\Modules\Fisheries\Enums\FishingSpotStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFishingSpotRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'base_price_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', Rule::enum(FishingSpotStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) ($this->input('currency') ?: 'PLN')),
            'status' => strtoupper((string) ($this->input('status') ?: FishingSpotStatus::Active->value)),
        ]);
    }
}
