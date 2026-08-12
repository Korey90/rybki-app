<?php

namespace App\Modules\Bookings\Actions;

use App\Modules\Bookings\Models\CancellationPolicy;
use App\Modules\Organizations\Models\Organization;

class ResolveCancellationPolicySnapshot
{
    /**
     * @return array{
     *     policy_id:int|null,
     *     name:string,
     *     free_cancellation_hours:int,
     *     late_refund_percentage:int
     * }
     */
    public function handle(Organization $organization): array
    {
        $policy = $organization->cancellationPolicies()
            ->where('is_default', true)
            ->oldest()
            ->first()
            ?? $organization->cancellationPolicies()->oldest()->first();

        if ($policy instanceof CancellationPolicy) {
            return [
                'policy_id' => $policy->id,
                'name' => $policy->name,
                'free_cancellation_hours' => $policy->free_cancellation_hours,
                'late_refund_percentage' => $policy->late_refund_percentage,
            ];
        }

        return [
            'policy_id' => null,
            'name' => 'Standard MVP',
            'free_cancellation_hours' => 24,
            'late_refund_percentage' => 0,
        ];
    }
}
