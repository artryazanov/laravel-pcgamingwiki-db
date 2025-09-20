<?php

namespace Tests\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsCreateTablesTest extends TestCase
{
    public function test_tables_are_created_and_legacy_columns_absent(): void
    {
        // Core
        $this->assertTrue(Schema::hasTable('pcgw_game_wikipages'));
        $this->assertTrue(Schema::hasTable('pcgw_games'));

        // Taxonomies
        $this->assertTrue(Schema::hasTable('pcgw_game_companies'));
        $this->assertTrue(Schema::hasTable('pcgw_game_platforms'));
        $this->assertTrue(Schema::hasTable('pcgw_game_genres'));
        $this->assertTrue(Schema::hasTable('pcgw_game_modes'));
        $this->assertTrue(Schema::hasTable('pcgw_game_series'));
        $this->assertTrue(Schema::hasTable('pcgw_game_engines'));

        // Pivots
        $this->assertTrue(Schema::hasTable('pcgw_game_game_company'));
        $this->assertTrue(Schema::hasTable('pcgw_game_game_platform'));
        $this->assertTrue(Schema::hasTable('pcgw_game_game_genre'));
        $this->assertTrue(Schema::hasTable('pcgw_game_game_mode'));
        $this->assertTrue(Schema::hasTable('pcgw_game_game_series'));
        $this->assertTrue(Schema::hasTable('pcgw_game_game_engine'));

        // Legacy columns were removed from pcgw_games
        $this->assertFalse(Schema::hasColumn('pcgw_games', 'page_name'));
        $this->assertFalse(Schema::hasColumn('pcgw_games', 'page_id'));
        $this->assertFalse(Schema::hasColumn('pcgw_games', 'title'));
        $this->assertFalse(Schema::hasColumn('pcgw_games', 'developers'));
        $this->assertFalse(Schema::hasColumn('pcgw_games', 'publishers'));
    }
}
