<?php

namespace App\Modules\Payments\Enums;

enum PaymentReconciliationRunStatus: string
{
    case Running = 'RUNNING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
