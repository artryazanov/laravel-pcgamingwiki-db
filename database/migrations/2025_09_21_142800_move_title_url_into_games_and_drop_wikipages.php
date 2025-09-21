<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Ensure columns exist on pcgw_games
        if (Schema::hasTable('pcgw_games')) {
            Schema::table('pcgw_games', function (Blueprint $table) {
                if (! Schema::hasColumn('pcgw_games', 'title')) {
                    $table->string('title')->nullable()->after('id');
                }
                if (! Schema::hasColumn('pcgw_games', 'pcgw_url')) {
                    $table->string('pcgw_url')->nullable()->after('title');
                }
            });
        }

        // 2) Backfill from pcgw_game_wikipages via wikipage_id
        if (Schema::hasTable('pcgw_games') && Schema::hasTable('pcgw_game_wikipages') && Schema::hasColumn('pcgw_games', 'wikipage_id')) {
            $driver = Schema::getConnection()->getDriverName();
            try {
                if ($driver === 'sqlite') {
                    DB::statement('UPDATE pcgw_games AS g SET title = (
                        SELECT w.title FROM pcgw_game_wikipages w WHERE w.id = g.wikipage_id
                    ) WHERE title IS NULL');
                    DB::statement('UPDATE pcgw_games AS g SET pcgw_url = (
                        SELECT w.pcgw_url FROM pcgw_game_wikipages w WHERE w.id = g.wikipage_id
                    ) WHERE pcgw_url IS NULL');
                } else {
                    // MySQL/Postgres syntax with JOIN
                    DB::statement('UPDATE pcgw_games g JOIN pcgw_game_wikipages w ON w.id = g.wikipage_id
                        SET g.title = COALESCE(g.title, w.title), g.pcgw_url = COALESCE(g.pcgw_url, w.pcgw_url)');
                }
            } catch (\Throwable $e) {
                // Best-effort backfill; ignore if driver-specific SQL fails in CI
            }
        }

        // 3) Drop FK and column wikipage_id from pcgw_games
        if (Schema::hasTable('pcgw_games') && Schema::hasColumn('pcgw_games', 'wikipage_id')) {
            Schema::table('pcgw_games', function (Blueprint $table) {
                try {
                    $table->dropConstrainedForeignId('wikipage_id');
                } catch (\Throwable $e) {
                    if (Schema::hasColumn('pcgw_games', 'wikipage_id')) {
                        try { $table->dropForeign(['wikipage_id']); } catch (\Throwable $e2) {}
                        $table->dropColumn('wikipage_id');
                    }
                }
            });
        }

        // 4) Drop the wikipages table entirely
        Schema::dropIfExists('pcgw_game_wikipages');
    }

    public function down(): void
    {
        // Recreate the wikipages table (minimal columns) and reintroduce FK on games
        if (! Schema::hasTable('pcgw_game_wikipages')) {
            Schema::create('pcgw_game_wikipages', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable();
                $table->string('pcgw_url')->nullable();
                $table->text('description')->nullable();
                $table->longText('wikitext')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('pcgw_games') && ! Schema::hasColumn('pcgw_games', 'wikipage_id')) {
            Schema::table('pcgw_games', function (Blueprint $table) {
                $table->foreignId('wikipage_id')->nullable()->constrained('pcgw_game_wikipages')->nullOnDelete();
            });
        }
    }
};
