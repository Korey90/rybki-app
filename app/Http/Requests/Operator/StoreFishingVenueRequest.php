<?php

namespace App\Http\Requests\Operator;

use App\Modules\Fisheries\Enums\FishingVenueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFishingVenueRequest extends FormRequest
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
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'venue_type' => ['required', Rule::enum(FishingVenueType::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'voivodeship' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'rules' => ['nullable', 'string', 'max:10000'],
            'check_in_instructions' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => strtoupper((string) ($this->input('country') ?: 'PL')),
            'venue_type' => strtoupper((string) ($this->input('venue_type') ?: FishingVenueType::Private->value)),
        ]);
    }
}
