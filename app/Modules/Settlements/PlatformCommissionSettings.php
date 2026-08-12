<?php

namespace App\Modules\Settlements;

use App\Models\User;
use App\Modules\Settlements\Models\PlatformCommissionSetting;

class PlatformCommissionSettings
{
    private const GLOBAL_SCOPE = 'global';

    public function global(): PlatformCommissionSetting
    {
        /** @var PlatformCommissionSetting $setting */
        $setting = PlatformCommissionSetting::query()->firstOrCreate(
            ['scope' => self::GLOBAL_SCOPE],
            ['default_commission_bps' => 0],
        );

        return $setting;
    }

    public function defaultCommissionBps(): int
    {
        return $this->global()->default_commission_bps;
    }

    public function setDefaultCommissionBps(int $commissionBps, ?User $updatedBy = null): PlatformCommissionSetting
    {
        $setting = $this->global();
        $setting->forceFill([
            'default_commission_bps' => max(0, min(10000, $commissionBps)),
            'updated_by_user_id' => $updatedBy?->id,
        ])->save();

        return $setting->refresh();
    }
}
