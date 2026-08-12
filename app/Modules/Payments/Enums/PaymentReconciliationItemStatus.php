<?php

namespace App\Modules\Payments\Enums;

enum PaymentReconciliationItemStatus: string
{
    case Ok = 'OK';
    case Mismatch = 'MISMATCH';
    case NeedsReview = 'NEEDS_REVIEW';
}
