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
        Schema::table('fishing_venues', function (Blueprint $table) {
            $table->string('moderation_reason', 500)->nullable()->after('published_at');
            $table->foreignId('moderated_by_user_id')->nullable()->after('moderation_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable()->after('moderated_by_user_id');
        });

        Schema::table('fishing_venue_media', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('sort_order');
            $table->string('hidden_reason', 500)->nullable()->after('hidden_at');
            $table->foreignId('hidden_by_user_id')->nullable()->after('hidden_reason')->constrained('users')->nullOnDelete();
            $table->index(['fishing_venue_id', 'hidden_at']);
        });

        Schema::table('fishing_spot_media', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('sort_order');
            $table->string('hidden_reason', 500)->nullable()->after('hidden_at');
            $table->foreignId('hidden_by_user_id')->nullable()->after('hidden_reason')->constrained('users')->nullOnDelete();
            $table->index(['fishing_spot_id', 'hidden_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fishing_spot_media', function (Blueprint $table) {
            $table->dropIndex(['fishing_spot_id', 'hidden_at']);
            $table->dropConstrainedForeignId('hidden_by_user_id');
            $table->dropColumn(['hidden_at', 'hidden_reason']);
        });

        Schema::table('fishing_venue_media', function (Blueprint $table) {
            $table->dropIndex(['fishing_venue_id', 'hidden_at']);
            $table->dropConstrainedForeignId('hidden_by_user_id');
            $table->dropColumn(['hidden_at', 'hidden_reason']);
        });

        Schema::table('fishing_venues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by_user_id');
            $table->dropColumn(['moderation_reason', 'moderated_at']);
        });
    }
};
