<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Payments\Models\Payment;
use App\Policies\Concerns\HandlesOrganizationAuthorization;

class PaymentPolicy
{
    use HandlesOrganizationAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->canUseOperatorPanel();
    }

    public function view(User $user, Payment $payment): bool
    {
        $payment->loadMissing('booking');

        return $user->is_admin
            || $payment->booking->user_id === $user->id
            || $this->managesOrganization($user, $payment->booking->organization_id);
    }

    public function reconcile(User $user): bool
    {
        return $user->is_admin;
    }
}
