<?php

namespace App\Jobs;

use App\Modules\Bookings\Actions\DispatchBookingNotification;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Enums\ReservationStatus;
use App\Modules\Bookings\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBookingReminderNotificationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $hoursBeforeStart = 24,
        public readonly int $windowMinutes = 60,
    ) {}

    public function handle(DispatchBookingNotification $dispatchBookingNotification): void
    {
        $windowStart = CarbonImmutable::instance(now())->addHours($this->hoursBeforeStart);
        $windowEnd = $windowStart->addMinutes($this->windowMinutes);

        Booking::query()
            ->with(['user', 'spotReservations.spot', 'venue'])
            ->where('status', BookingStatus::Confirmed->value)
            ->whereHas('spotReservations', function ($query) use ($windowEnd, $windowStart): void {
                $query
                    ->where('status', ReservationStatus::Confirmed->value)
                    ->where('starts_at', '>', $windowStart->toDateTimeString())
                    ->where('starts_at', '<=', $windowEnd->toDateTimeString());
            })
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($dispatchBookingNotification): void {
                foreach ($bookings as $booking) {
                    $dispatchBookingNotification->reminder($booking);
                }
            });
    }
}
