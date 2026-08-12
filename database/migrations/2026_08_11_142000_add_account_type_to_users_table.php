<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('account_type')->default('ANGLER')->index();
        });

        DB::table('users')
            ->whereIn('id', function ($query): void {
                $query->select('user_id')
                    ->from('organization_members')
                    ->whereIn('role', ['OWNER', 'MANAGER'])
                    ->distinct();
            })
            ->update(['account_type' => 'VENUE_OWNER']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('account_type');
        });
    }
};
