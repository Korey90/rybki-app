<?php

namespace App\Modules\Settlements\Enums;

enum SettlementItemType: string
{
    case Payment = 'PAYMENT';
    case Refund = 'REFUND';
}
