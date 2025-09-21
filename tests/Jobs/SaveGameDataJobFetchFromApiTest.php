<?php

namespace Tests\Jobs;

use Artryazanov\PCGamingWiki\Jobs\SaveGameDataJob;
use Artryazanov\PCGamingWiki\Models\Company;
use Artryazanov\PCGamingWiki\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaveGameDataJobFetchFromApiTest extends TestCase
{
    public function test_enriches_missing_fields_via_cargoquery_and_persists(): void
    {
        $title = 'The Test Game (PC)';
        $pcgwUrl = 'https://www.pcgamingwiki.com/wiki/The_Test_Game_(PC)';

        // Fake Cargo API returning infobox fields for this page
        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::response([
                'cargoquery' => [
                    [
                        'title' => [
                            'Developers' => 'Dev A; Dev B',
                            'Publisher' => 'Pub A',
                            'Released' => '2017-05-01',
                            'Cover_URL' => 'File:Cover.png',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'title' => $title,
            'pcgw_url' => $pcgwUrl,
            // No developers/publishers/release/cover provided: job should fetch them
        ];

        (new SaveGameDataJob($payload))->handle();

        // Game created with enriched data
        $game = Game::query()->where('pcgw_url', $pcgwUrl)->first();
        $this->assertNotNull($game);
        $this->assertSame($title, $game->title);
        $this->assertSame($pcgwUrl, $game->pcgw_url);
        $this->assertNotNull($game);
        $this->assertSame('The Test Game', $game->clean_title);
        $this->assertSame(2017, $game->release_year);
        $this->assertSame('2017-05-01', $game->release_date);
        $this->assertSame('https://www.pcgamingwiki.com/wiki/Special:FilePath/File%3ACover.png', $game->cover_url);

        // Companies created from enriched data
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
    }
}
