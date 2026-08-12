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

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(<<<'SQL'
            ALTER TABLE spot_reservations
            ADD CONSTRAINT spot_reservations_no_active_overlap
            EXCLUDE USING gist (
                fishing_spot_id WITH =,
                tsrange(starts_at, ends_at, '[)') WITH &&
            )
            WHERE (status IN ('HOLD', 'CONFIRMED'))
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE spot_reservations DROP CONSTRAINT IF EXISTS spot_reservations_no_active_overlap');
    }
};
