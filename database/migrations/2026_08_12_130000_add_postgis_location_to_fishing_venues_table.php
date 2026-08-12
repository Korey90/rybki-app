<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('ALTER TABLE fishing_venues ADD COLUMN IF NOT EXISTS location geography(Point, 4326)');
        DB::statement(<<<'SQL'
            UPDATE fishing_venues
            SET location = ST_SetSRID(ST_MakePoint(longitude::double precision, latitude::double precision), 4326)::geography
            WHERE latitude IS NOT NULL
              AND longitude IS NOT NULL
        SQL);
        DB::statement('CREATE INDEX IF NOT EXISTS fishing_venues_location_gix ON fishing_venues USING GIST (location)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS fishing_venues_location_gix');
        DB::statement('ALTER TABLE fishing_venues DROP COLUMN IF EXISTS location');
    }
};
