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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('booking_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('currency', 3)->default('PLN');
            $table->unsignedInteger('subtotal_amount');
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('total_amount');
            $table->unsignedInteger('commission_bps')->default(0);
            $table->unsignedInteger('platform_fee_amount')->default(0);
            $table->unsignedInteger('operator_amount');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->json('cancellation_policy_snapshot')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['organization_id', 'created_at']);
            $table->index(['fishing_venue_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('spot_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fishing_spot_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status');
            $table->dateTime('hold_expires_at')->nullable();
            $table->timestamps();

            $table->index(['fishing_spot_id', 'starts_at', 'ends_at']);
            $table->index(['fishing_spot_id', 'status', 'hold_expires_at']);
            $table->index(['booking_id', 'status']);
        });

        Schema::create('booking_price_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_amount');
            $table->unsignedInteger('total_amount');
            $table->timestamps();

            $table->index(['booking_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_price_lines');
        Schema::dropIfExists('spot_reservations');
        Schema::dropIfExists('bookings');
    }
};
