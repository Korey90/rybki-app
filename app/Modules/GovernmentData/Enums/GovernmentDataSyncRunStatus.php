<?php

namespace App\Modules\GovernmentData\Enums;

enum GovernmentDataSyncRunStatus: string
{
    case Running = 'RUNNING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
