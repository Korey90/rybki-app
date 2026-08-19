<?php

use App\Modules\Reviews\Enums\FishingVenueReviewStatus;
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
        Schema::create('fishing_venue_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'fishing_venue_id']);
            $table->index(['fishing_venue_id', 'created_at']);
        });

        Schema::create('fishing_venue_reviews', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 120)->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default(FishingVenueReviewStatus::Published->value);
            $table->string('hidden_reason', 500)->nullable();
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('operator_response')->nullable();
            $table->foreignId('operator_responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('operator_responded_at')->nullable();
            $table->timestamps();

            $table->index(['fishing_venue_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fishing_venue_reviews');
        Schema::dropIfExists('fishing_venue_favorites');
    }
};
