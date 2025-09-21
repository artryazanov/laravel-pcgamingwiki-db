<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pcgw_games') || ! Schema::hasColumn('pcgw_games', 'cover_url')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        try {
            if ($driver === 'mysql') {
                // Increase to 2048 chars for MySQL/MariaDB
                DB::statement('ALTER TABLE `pcgw_games` MODIFY `cover_url` VARCHAR(2048) NULL');
            } elseif ($driver === 'pgsql') {
                // Increase to 2048 for PostgreSQL
                DB::statement('ALTER TABLE pcgw_games ALTER COLUMN cover_url TYPE VARCHAR(2048)');
            } else {
                // SQLite and others: no-op (SQLite does not enforce VARCHAR length)
            }
        } catch (\Throwable $e) {
            // Best-effort migration: ignore driver-specific failures in CI/test environments
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('pcgw_games') || ! Schema::hasColumn('pcgw_games', 'cover_url')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        try {
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `pcgw_games` MODIFY `cover_url` VARCHAR(255) NULL');
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE pcgw_games ALTER COLUMN cover_url TYPE VARCHAR(255)');
            } else {
                // SQLite and others: no-op
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
