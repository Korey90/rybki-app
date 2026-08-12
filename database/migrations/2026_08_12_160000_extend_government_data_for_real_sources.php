<?php

use App\Modules\GovernmentData\Enums\GovernmentDataSyncRunStatus;
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
        Schema::table('data_sources', function (Blueprint $table): void {
            $table->string('external_dataset_id')->nullable()->after('dataset_url');
            $table->string('default_format', 16)->nullable()->after('external_dataset_id');
            $table->boolean('sync_enabled')->default(false)->after('default_format');
            $table->json('metadata')->nullable()->after('sync_enabled');
        });

        Schema::table('government_venue_records', function (Blueprint $table): void {
            $table->json('normalized_payload')->nullable()->after('source_payload');
            $table->json('quality_flags')->nullable()->after('normalized_payload');
            $table->unsignedTinyInteger('quality_score')->default(0)->after('quality_flags');
            $table->json('match_candidates')->nullable()->after('quality_score');
            $table->unsignedTinyInteger('match_confidence')->default(0)->after('match_candidates');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('imported_fishing_venue_id')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->timestamp('imported_at')->nullable()->after('reviewed_at');
            $table->timestamp('skipped_at')->nullable()->after('imported_at');

            $table->index(['quality_score', 'status'], 'government_records_quality_status_index');
        });

        Schema::create('government_data_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(GovernmentDataSyncRunStatus::Running->value);
            $table->string('resource_external_id')->nullable();
            $table->string('resource_title')->nullable();
            $table->string('resource_url')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_staged')->default(0);
            $table->unsignedInteger('records_needing_review')->default(0);
            $table->unsignedInteger('records_with_candidates')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('government_data_sync_runs');

        Schema::table('government_venue_records', function (Blueprint $table): void {
            $table->dropIndex('government_records_quality_status_index');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn([
                'normalized_payload',
                'quality_flags',
                'quality_score',
                'match_candidates',
                'match_confidence',
                'reviewed_at',
                'imported_at',
                'skipped_at',
            ]);
        });

        Schema::table('data_sources', function (Blueprint $table): void {
            $table->dropColumn([
                'external_dataset_id',
                'default_format',
                'sync_enabled',
                'metadata',
            ]);
        });
    }
};
