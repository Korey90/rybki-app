<?php

namespace App\Modules\Settlements\Enums;

enum SettlementPeriodStatus: string
{
    case Draft = 'DRAFT';
    case Approved = 'APPROVED';
    case Processing = 'PROCESSING';
    case Paid = 'PAID';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
}
