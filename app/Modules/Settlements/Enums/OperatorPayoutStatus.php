<?php

namespace App\Modules\Settlements\Enums;

enum OperatorPayoutStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Processing = 'PROCESSING';
    case Paid = 'PAID';
    case Failed = 'FAILED';
    case Cancelled = 'CANCELLED';
}
