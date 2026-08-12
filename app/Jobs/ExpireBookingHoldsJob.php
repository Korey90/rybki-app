<?php

namespace App\Jobs;

use App\Modules\Bookings\Actions\ExpireBookingHolds;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireBookingHoldsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ExpireBookingHolds $expireBookingHolds): void
    {
        $expireBookingHolds->handle();
    }
}
