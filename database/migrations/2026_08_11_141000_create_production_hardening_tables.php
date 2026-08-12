<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['actor_user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });

        Schema::create('cancellation_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('free_cancellation_hours')->default(24);
            $table->unsignedTinyInteger('late_refund_percentage')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'is_default']);
        });

        Schema::create('payment_reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('status')->default('COMPLETED');
            $table->unsignedInteger('checked_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_reconciliation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_reconciliation_run_id');
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('reason');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('payment_reconciliation_run_id', 'recon_items_run_fk')
                ->references('id')
                ->on('payment_reconciliation_runs')
                ->cascadeOnDelete();
            $table->index(['payment_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_items');
        Schema::dropIfExists('payment_reconciliation_runs');
        Schema::dropIfExists('cancellation_policies');
        Schema::dropIfExists('audit_logs');
    }
};
