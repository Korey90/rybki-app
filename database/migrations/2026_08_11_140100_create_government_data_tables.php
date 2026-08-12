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
        Schema::create('data_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('dataset_name');
            $table->string('dataset_url')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'dataset_name'], 'data_sources_provider_dataset_unique');
        });

        Schema::create('government_venue_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('venue_type')->default('OTHER_PUBLIC');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('voivodeship')->nullable();
            $table->string('country', 2)->default('PL');
            $table->json('source_payload');
            $table->timestamp('source_updated_at')->nullable();
            $table->string('status')->default('PENDING');
            $table->foreignId('matched_fishing_venue_id')->nullable()->constrained('fishing_venues')->nullOnDelete();
            $table->foreignId('imported_fishing_venue_id')->nullable()->constrained('fishing_venues')->nullOnDelete();
            $table->timestamps();

            $table->unique(['data_source_id', 'external_id'], 'government_records_source_external_unique');
            $table->index(['status', 'created_at']);
            $table->index(['matched_fishing_venue_id', 'status'], 'government_records_match_status_index');
        });

        Schema::create('fishing_venue_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->json('source_payload');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['data_source_id', 'external_id'], 'venue_sources_source_external_unique');
            $table->unique(['fishing_venue_id', 'data_source_id', 'external_id'], 'venue_sources_venue_source_external_unique');
        });

        Schema::create('fishing_venue_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('PENDING');
            $table->text('evidence')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['fishing_venue_id', 'organization_id'], 'venue_claims_venue_organization_unique');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fishing_venue_claims');
        Schema::dropIfExists('fishing_venue_sources');
        Schema::dropIfExists('government_venue_records');
        Schema::dropIfExists('data_sources');
    }
};
