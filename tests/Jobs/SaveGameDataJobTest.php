<?php

namespace Tests\Jobs;

use Artryazanov\PCGamingWiki\Jobs\SaveGameDataJob;
use Artryazanov\PCGamingWiki\Models\Company;
use Artryazanov\PCGamingWiki\Models\Game;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaveGameDataJobTest extends TestCase
{
    public function test_persists_wikipage_game_and_companies_with_roles_and_helpers(): void
    {
        $title = 'The Test Game (PC)';
        $pcgwUrl = 'https://www.pcgamingwiki.com/wiki/The_Test_Game_(PC)';

        $payload = [
            'title' => $title,
            'pcgw_url' => $pcgwUrl,
            'developers' => 'Dev A; Dev B',
            'publishers' => 'Pub A',
            'release_date' => '2017-05-01',
            'cover_url' => 'File:Cover.png',
        ];

        // Run job
        (new SaveGameDataJob($payload))->handle();

        // Game created
        $game = Game::query()->where('pcgw_url', $pcgwUrl)->first();
        $this->assertNotNull($game);
        $this->assertSame($title, $game->title);
        $this->assertSame($pcgwUrl, $game->pcgw_url);

        // Ensure helpers
        $this->assertNotNull($game);
        $this->assertSame('The Test Game', $game->clean_title, 'Clean title should strip parentheses');
        $this->assertSame(2017, $game->release_year);
        $this->assertSame('2017-05-01', $game->release_date);
        $this->assertSame('https://www.pcgamingwiki.com/wiki/Special:FilePath/File%3ACover.png', $game->cover_url);

        // Companies created
        $this->assertTrue(Company::query()->where('name', 'Dev A')->exists());
        $this->assertTrue(Company::query()->where('name', 'Dev B')->exists());
        $this->assertTrue(Company::query()->where('name', 'Pub A')->exists());

        // Pivots with roles
        $pivots = DB::table('pcgw_game_game_company')->get()->map(fn($r) => [
            'company_id' => $r->company_id,
            'role' => $r->role,
        ])->values();
        $this->assertCount(3, $pivots);

        $devAId = Company::where('name', 'Dev A')->value('id');
        $devBId = Company::where('name', 'Dev B')->value('id');
        $pubAId = Company::where('name', 'Pub A')->value('id');

        $this->assertContains(['company_id' => $devAId, 'role' => 'developer'], $pivots->toArray());
        $this->assertContains(['company_id' => $devBId, 'role' => 'developer'], $pivots->toArray());
        $this->assertContains(['company_id' => $pubAId, 'role' => 'publisher'], $pivots->toArray());

        // Idempotency: run again, ensure no duplicate pivots
        (new SaveGameDataJob($payload))->handle();
        $this->assertCount(3, DB::table('pcgw_game_game_company')->get());
    }
}
