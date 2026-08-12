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
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fishing_spot_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('minimum_booking_minutes')->default(60);
            $table->unsignedInteger('maximum_booking_minutes')->nullable();
            $table->timestamps();

            $table->index(['fishing_spot_id', 'day_of_week']);
            $table->index(['valid_from', 'valid_until']);
        });

        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fishing_spot_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fishing_spot_id', 'starts_at', 'ends_at']);
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fishing_spot_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedInteger('price_amount');
            $table->string('currency', 3)->default('PLN');
            $table->unsignedInteger('minimum_duration_minutes')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['fishing_spot_id', 'starts_at', 'ends_at']);
            $table->index(['fishing_spot_id', 'day_of_week', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('availability_blocks');
        Schema::dropIfExists('availability_rules');
    }
};
