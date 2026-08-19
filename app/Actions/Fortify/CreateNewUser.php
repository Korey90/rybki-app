<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserAccountType;
use App\Models\User;
use App\Modules\Legal\Actions\RecordLegalAcceptance;
use App\Modules\Legal\Enums\LegalDocumentType;
use App\Modules\Legal\Support\CurrentLegalDocuments;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly CurrentLegalDocuments $currentLegalDocuments,
        private readonly RecordLegalAcceptance $recordLegalAcceptance
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'account_type' => ['required', new Enum(UserAccountType::class)],
            'password' => $this->passwordRules(),
            'legal_terms_accepted' => ['accepted'],
            'privacy_policy_accepted' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'account_type' => UserAccountType::from($input['account_type']),
            'password' => $input['password'],
        ]);

        $request = request();
        $marketingConsent = (bool) ($input['marketing_consent'] ?? false);

        $this->recordLegalAcceptance->handle(
            LegalDocumentType::Terms,
            'registration',
            user: $user,
            request: $request,
        );
        $this->recordLegalAcceptance->handle(
            LegalDocumentType::PrivacyPolicy,
            'registration',
            user: $user,
            request: $request,
            marketingConsent: $marketingConsent,
        );

        if ($marketingConsent) {
            $privacyDocument = $this->currentLegalDocuments->get(LegalDocumentType::PrivacyPolicy);

            $user->forceFill([
                'marketing_consent_at' => now(),
                'marketing_consent_document_version' => $privacyDocument->version,
            ])->save();
        }

        return $user;
    }
}
