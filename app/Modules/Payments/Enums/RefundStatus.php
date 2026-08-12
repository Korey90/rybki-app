<?php

namespace App\Modules\Payments\Enums;

enum RefundStatus: string
{
    case Created = 'CREATED';
    case Processing = 'PROCESSING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
}
