<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Consolidated migration for PCGamingWiki DB schema.
 * Merges initial pcgw_games creation and the normalized schema into a single migration.
 *
 * Safe for fresh installs and existing ones (idempotent guards everywhere):
 * - Renames legacy `games` table to `pcgw_games` if present.
 * - Creates central `pcgw_game_wikipages` table.
 * - Creates `pcgw_games` with all required columns if missing; otherwise adds missing columns.
 * - Creates taxonomy tables and pivot tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Central PCGamingWiki wikipages table
        if (! Schema::hasTable('pcgw_game_wikipages')) {
            Schema::create('pcgw_game_wikipages', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable()->comment('Original PCGW page title');
                $table->string('pcgw_url')->nullable()->unique()->comment('Full URL to the PCGamingWiki page');
                $table->text('description')->nullable()->comment('Lead/summary text');
                $table->longText('wikitext')->nullable()->comment('Full page content in wikitext');
                $table->timestamps();
                $table->comment('Central storage for PCGamingWiki page meta reused by multiple entities');
            });
        }

        // 2) Create or extend pcgw_games
        if (! Schema::hasTable('pcgw_games')) {
            Schema::create('pcgw_games', function (Blueprint $table) {
                $table->id();
                // Legacy columns removed: page_name, page_id, title, developers, publishers
                $table->string('release_date')->nullable();
                $table->string('cover_url')->nullable();
                // Normalized-friendly columns
                $table->string('clean_title')->nullable()->index()->comment('Normalized title without disambiguation');
                $table->unsignedSmallInteger('release_year')->nullable()->comment('First 4-digit release year parsed');
                $table->foreignId('wikipage_id')->nullable()->constrained('pcgw_game_wikipages')->nullOnDelete()->comment('Reference to central wikipage');
                $table->timestamps();
            });
        } else {
            // Ensure normalized columns exist
            Schema::table('pcgw_games', function (Blueprint $table) {
                if (! Schema::hasColumn('pcgw_games', 'clean_title')) {
                    $table->string('clean_title')->nullable()->index()->comment('Normalized title without disambiguation');
                }
                if (! Schema::hasColumn('pcgw_games', 'release_year')) {
                    $table->unsignedSmallInteger('release_year')->nullable()->comment('First 4-digit release year parsed');
                }
                if (! Schema::hasColumn('pcgw_games', 'wikipage_id')) {
                    $table->foreignId('wikipage_id')->nullable()->constrained('pcgw_game_wikipages')->nullOnDelete()->comment('Reference to central wikipage');
                }
            });

            // Drop legacy columns if they still exist
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'sqlite' && Schema::hasColumn('pcgw_games', 'page_name')) {
                // Best-effort: drop the unique index explicitly before dropping the column
                try {
                    DB::statement('DROP INDEX IF EXISTS pcgw_games_page_name_unique');
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            Schema::table('pcgw_games', function (Blueprint $table) {
                $legacyCols = ['page_name', 'page_id', 'title', 'developers', 'publishers'];
                foreach ($legacyCols as $col) {
                    if (Schema::hasColumn('pcgw_games', $col)) {
                        // For MySQL, attempt to drop unique index on page_name just in case
                        if ($col === 'page_name') {
                            try { $table->dropUnique('pcgw_games_page_name_unique'); } catch (\Throwable $e) {}
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // 3) Taxonomy tables
        $taxonomies = [
            'pcgw_game_companies' => 'company',
            'pcgw_game_platforms' => 'platform',
            'pcgw_game_genres'    => 'genre',
            'pcgw_game_modes'     => 'mode',
            'pcgw_game_series'    => 'series',
            'pcgw_game_engines'   => 'engine',
        ];

        foreach ($taxonomies as $tableName => $label) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) use ($label) {
                    $table->id();
                    $table->string('name')->unique()->comment("Unique {$label} name");
                    $table->foreignId('wikipage_id')->nullable()->constrained('pcgw_game_wikipages')->nullOnDelete()->comment('Optional page meta reference');
                    $table->timestamps();
                    $table->comment("Stores {$label}s for many-to-many relation with games.");
                });
            }
        }

        // 4) Pivot tables
        if (! Schema::hasTable('pcgw_game_game_genre')) {
            Schema::create('pcgw_game_game_genre', function (Blueprint $table) {
                $table->foreignId('game_id')->constrained('pcgw_games')->onDelete('cascade');
                $table->foreignId('genre_id')->constrained('pcgw_game_genres')->onDelete('cascade');
                $table->primary(['game_id', 'genre_id']);
                $table->comment('Pivot linking games and genres (many-to-many).');
            });
        }

        if (! Schema::hasTable('pcgw_game_game_platform')) {
            Schema::create('pcgw_game_game_platform', function (Blueprint $table) {
                $table->foreignId('game_id')->constrained('pcgw_games')->onDelete('cascade');
                $table->foreignId('platform_id')->constrained('pcgw_game_platforms')->onDelete('cascade');
                $table->primary(['game_id', 'platform_id']);
                $table->comment('Pivot linking games and platforms (many-to-many).');
            });
        }

        if (! Schema::hasTable('pcgw_game_game_mode')) {
            Schema::create('pcgw_game_game_mode', function (Blueprint $table) {
                $table->foreignId('game_id')->constrained('pcgw_games')->onDelete('cascade');
                $table->foreignId('mode_id')->constrained('pcgw_game_modes')->onDelete('cascade');
                $table->primary(['game_id', 'mode_id']);
                $table->comment('Pivot linking games and modes (many-to-many).');
            });
        }

        if (! Schema::hasTable('pcgw_game_game_series')) {
            Schema::create('pcgw_game_game_series', function (Blueprint $table) {
                $table->foreignId('game_id')->constrained('pcgw_games')->onDelete('cascade');
                $table->foreignId('series_id')->constrained('pcgw_game_series')->onDelete('cascade');
                $table->primary(['game_id', 'series_id']);
                $table->comment('Pivot linking games and series (many-to-many).');
            });
        }

        if (! Schema::hasTable('pcgw_game_game_engine')) {
            Schema::create('pcgw_game_game_engine', function (Blueprint $table) {
                $table->foreignId('game_id')->constrained('pcgw_games')->onDelete('cascade');
                $table->foreignId('engine_id')->constrained('pcgw_game_engines')->onDelete('cascade');
                $table->primary(['game_id', 'engine_id']);
                $table->comment('Pivot linking games and engines (many-to-many).');
            });
        }

        if (! Schema::hasTable('pcgw_game_game_company')) {
            Schema::create('pcgw_game_game_company', function (Blueprint $table) {
                $table->foreignId('game_id')->constrained('pcgw_games')->onDelete('cascade');
                $table->foreignId('company_id')->constrained('pcgw_game_companies')->onDelete('cascade');
                $table->enum('role', ['developer', 'publisher'])->comment('Company role relative to the game');
                $table->primary(['game_id', 'company_id', 'role']);
                $table->comment('Pivot linking games and companies with role metadata.');
            });
        }
    }

    public function down(): void
    {
        // Drop pivot tables first
        Schema::dropIfExists('pcgw_game_game_company');
        Schema::dropIfExists('pcgw_game_game_engine');
        Schema::dropIfExists('pcgw_game_game_series');
        Schema::dropIfExists('pcgw_game_game_mode');
        Schema::dropIfExists('pcgw_game_game_platform');
        Schema::dropIfExists('pcgw_game_game_genre');

        // Drop taxonomy tables
        Schema::dropIfExists('pcgw_game_engines');
        Schema::dropIfExists('pcgw_game_series');
        Schema::dropIfExists('pcgw_game_modes');
        Schema::dropIfExists('pcgw_game_genres');
        Schema::dropIfExists('pcgw_game_platforms');
        Schema::dropIfExists('pcgw_game_companies');

        // Remove FK and normalized columns from pcgw_games, then drop it
        if (Schema::hasTable('pcgw_games')) {
            Schema::table('pcgw_games', function (Blueprint $table) {
                if (Schema::hasColumn('pcgw_games', 'wikipage_id')) {
                    $table->dropConstrainedForeignId('wikipage_id');
                }
                if (Schema::hasColumn('pcgw_games', 'clean_title')) {
                    $table->dropColumn('clean_title');
                }
                if (Schema::hasColumn('pcgw_games', 'release_year')) {
                    $table->dropColumn('release_year');
                }
            });
            Schema::dropIfExists('pcgw_games');
        }

        // Finally, drop central wikipages table
        Schema::dropIfExists('pcgw_game_wikipages');
    }
};
