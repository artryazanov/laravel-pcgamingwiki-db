<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'pcgw_game_companies',
            'pcgw_game_engines',
            'pcgw_game_genres',
            'pcgw_game_modes',
            'pcgw_game_platforms',
            'pcgw_game_series',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'wikipage_id')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    try {
                        // Drops the foreign key constraint and the column
                        $table->dropConstrainedForeignId('wikipage_id');
                    } catch (\Throwable $e) {
                        // Fallback for drivers without named constraints
                        if (Schema::hasColumn($tableName, 'wikipage_id')) {
                            $table->dropColumn('wikipage_id');
                        }
                    }
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'pcgw_game_companies',
            'pcgw_game_engines',
            'pcgw_game_genres',
            'pcgw_game_modes',
            'pcgw_game_platforms',
            'pcgw_game_series',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'wikipage_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('wikipage_id')
                        ->nullable()
                        ->constrained('pcgw_game_wikipages')
                        ->nullOnDelete()
                        ->comment('Optional page meta reference');
                });
            }
        }
    }
};
