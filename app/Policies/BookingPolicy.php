<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Bookings\Enums\BookingStatus;
use App\Modules\Bookings\Models\Booking;
use App\Policies\Concerns\HandlesOrganizationAuthorization;

class BookingPolicy
{
    use HandlesOrganizationAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->canUseOperatorPanel();
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->is_admin
            || $booking->user_id === $user->id
            || $this->managesOrganization($user, $booking->organization_id);
    }

    public function viewAsCustomer(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }

    public function viewAsOperator(User $user, Booking $booking): bool
    {
        return $user->is_admin || $this->managesOrganization($user, $booking->organization_id);
    }

    public function cancelAsCustomer(User $user, Booking $booking): bool
    {
        return $this->viewAsCustomer($user, $booking)
            && in_array($booking->status, [
                BookingStatus::PaymentPending,
                BookingStatus::Confirmed,
            ], true);
    }

    public function cancelAsOperator(User $user, Booking $booking): bool
    {
        return $this->viewAsOperator($user, $booking)
            && in_array($booking->status, [
                BookingStatus::PaymentPending,
                BookingStatus::Confirmed,
            ], true);
    }
}
