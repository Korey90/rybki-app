<?php

namespace App\Modules\Organizations\Actions;

use App\Enums\UserAccountType;
use App\Models\User;
use App\Modules\Organizations\Enums\OrganizationMemberRole;
use App\Modules\Organizations\Enums\OrganizationVerificationStatus;
use App\Modules\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;

class CreateOrganization
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $owner, array $attributes): Organization
    {
        return DB::transaction(function () use ($attributes, $owner): Organization {
            $organization = Organization::create([
                'name' => $attributes['name'],
                'legal_name' => $attributes['legal_name'] ?? null,
                'tax_id' => $attributes['tax_id'] ?? null,
                'regon' => $attributes['regon'] ?? null,
                'country' => $attributes['country'] ?? 'PL',
                'address' => $attributes['address'] ?? null,
                'verification_status' => OrganizationVerificationStatus::Pending,
            ]);

            $organization->members()->create([
                'user_id' => $owner->id,
                'role' => OrganizationMemberRole::Owner,
            ]);

            $owner->forceFill(['account_type' => UserAccountType::VenueOwner])->save();

            return $organization;
        });
    }
}
