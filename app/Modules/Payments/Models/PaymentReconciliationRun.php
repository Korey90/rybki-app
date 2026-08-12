<?php

namespace App\Modules\Payments\Models;

use App\Modules\Payments\Enums\PaymentProvider;
use App\Modules\Payments\Enums\PaymentReconciliationRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PaymentProvider $provider
 * @property PaymentReconciliationRunStatus $status
 * @property int $checked_count
 * @property int $mismatch_count
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property array<string, mixed>|null $summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'provider',
    'status',
    'checked_count',
    'mismatch_count',
    'started_at',
    'finished_at',
    'summary',
])]
class PaymentReconciliationRun extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentReconciliationRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary' => 'array',
        ];
    }

    /**
     * @return HasMany<PaymentReconciliationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PaymentReconciliationItem::class);
    }
}
