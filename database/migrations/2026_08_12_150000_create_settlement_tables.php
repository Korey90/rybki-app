<?php

use App\Modules\Settlements\Enums\OperatorPayoutStatus;
use App\Modules\Settlements\Enums\SettlementPeriodStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('platform_commission_settings', function (Blueprint $table) {
            $table->id();
            $table->string('scope')->unique()->default('global');
            $table->unsignedSmallInteger('default_commission_bps')->default(0);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('platform_commission_settings')->insert([
            'scope' => 'global',
            'default_commission_bps' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('settlement_periods', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default(SettlementPeriodStatus::Draft->value);
            $table->string('currency', 3)->default('PLN');
            $table->unsignedInteger('gross_amount')->default(0);
            $table->unsignedInteger('platform_fee_amount')->default(0);
            $table->unsignedInteger('refund_amount')->default(0);
            $table->integer('net_payout_amount')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->unsignedInteger('refunds_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('provider_payout_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'period_start', 'period_end', 'currency']);
            $table->index(['status', 'period_end']);
            $table->index(['provider', 'status']);
        });

        Schema::create('settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('settlement_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('gross_amount')->default(0);
            $table->unsignedInteger('platform_fee_amount')->default(0);
            $table->unsignedInteger('refund_amount')->default(0);
            $table->integer('operator_amount')->default(0);
            $table->string('currency', 3);
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['settlement_period_id', 'type']);
            $table->index(['occurred_at', 'type']);
            $table->unique(['settlement_period_id', 'payment_id', 'type']);
            $table->unique(['settlement_period_id', 'refund_id', 'type']);
        });

        Schema::create('operator_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('settlement_period_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->nullable();
            $table->string('status')->default(OperatorPayoutStatus::Pending->value);
            $table->integer('amount')->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->string('provider_payout_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operator_payouts');
        Schema::dropIfExists('settlement_items');
        Schema::dropIfExists('settlement_periods');
        Schema::dropIfExists('platform_commission_settings');
    }
};
