<?php

namespace App\Modules\Legal\Enums;

enum LegalDocumentType: string
{
    case Terms = 'TERMS';
    case PrivacyPolicy = 'PRIVACY_POLICY';
    case CancellationRules = 'CANCELLATION_RULES';
    case Contact = 'CONTACT';
    case OperatorInfo = 'OPERATOR_INFO';

    public function slug(): string
    {
        return match ($this) {
            self::Terms => 'regulamin',
            self::PrivacyPolicy => 'polityka-prywatnosci',
            self::CancellationRules => 'zasady-anulowania',
            self::Contact => 'kontakt',
            self::OperatorInfo => 'dla-operatorow',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Terms => 'Regulamin',
            self::PrivacyPolicy => 'Polityka prywatności',
            self::CancellationRules => 'Zasady anulowania',
            self::Contact => 'Kontakt',
            self::OperatorInfo => 'Dla operatorów',
        };
    }
}
