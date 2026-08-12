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
        Schema::create('fishing_venues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('venue_type')->default('PRIVATE');
            $table->string('commercial_status')->default('BOOKABLE');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('voivodeship')->nullable();
            $table->string('country', 2)->default('PL');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('rules')->nullable();
            $table->text('check_in_instructions')->nullable();
            $table->string('status')->default('DRAFT');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['status', 'commercial_status']);
            $table->index(['city', 'voivodeship']);
        });

        Schema::create('fishing_spots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity')->default(1);
            $table->unsignedInteger('base_price_amount')->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->string('status')->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fishing_venue_id', 'status']);
            $table->index(['fishing_venue_id', 'sort_order']);
        });

        Schema::create('fishing_venue_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->string('type')->default('IMAGE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fishing_venue_id', 'sort_order']);
        });

        Schema::create('fishing_spot_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fishing_spot_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['fishing_spot_id', 'sort_order']);
        });

        Schema::create('fish_species', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('fishing_venue_fish_species', function (Blueprint $table) {
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fish_species_id')->constrained()->cascadeOnDelete();

            $table->primary(['fishing_venue_id', 'fish_species_id']);
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('fishing_venue_amenity', function (Blueprint $table) {
            $table->foreignId('fishing_venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();

            $table->primary(['fishing_venue_id', 'amenity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fishing_venue_amenity');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('fishing_venue_fish_species');
        Schema::dropIfExists('fish_species');
        Schema::dropIfExists('fishing_spot_media');
        Schema::dropIfExists('fishing_venue_media');
        Schema::dropIfExists('fishing_spots');
        Schema::dropIfExists('fishing_venues');
    }
};
