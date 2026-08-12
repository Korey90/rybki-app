<?php

namespace App\Modules\Payments\Models;

use App\Modules\Payments\Enums\PaymentReconciliationItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_reconciliation_run_id
 * @property int $payment_id
 * @property PaymentReconciliationItemStatus $status
 * @property string $reason
 * @property array<string, mixed>|null $details
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'payment_reconciliation_run_id',
    'payment_id',
    'status',
    'reason',
    'details',
])]
class PaymentReconciliationItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentReconciliationItemStatus::class,
            'details' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PaymentReconciliationRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PaymentReconciliationRun::class, 'payment_reconciliation_run_id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
