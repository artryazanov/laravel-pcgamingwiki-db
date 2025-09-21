<?php

namespace Tests\Jobs;

use Artryazanov\PCGamingWiki\Jobs\SaveGameDataJob;
use Artryazanov\PCGamingWiki\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaveGameDataJobExternalLinksTest extends TestCase
{
    public function test_parses_and_saves_external_links_from_infobox_icons(): void
    {
        $html = '<table class="vertical-navbox template-infobox" id="infobox-game">
    <caption class="template-infobox-title">Oddmar</caption>
<tbody><tr>
        <td class="template-infobox-cover" colspan="2"><a href="/wiki/File:Oddmar_cover.jpg" class="image"><img alt="Oddmar cover" src="https://thumbnails.pcgamingwiki.com/2/25/Oddmar_cover.jpg/300px-Oddmar_cover.jpg" decoding="async" width="300" height="300" srcset="https://thumbnails.pcgamingwiki.com/2/25/Oddmar_cover.jpg/450px-Oddmar_cover.jpg 1.5x, https://thumbnails.pcgamingwiki.com/2/25/Oddmar_cover.jpg/600px-Oddmar_cover.jpg 2x" data-file-width="800" data-file-height="800"></a></td>
    </tr>
<tr>
    <td class="template-infobox-icons" colspan="2"><div title="Official site" class="plainlinks template-infobox-icon svg-icon infobox-official-site"><a href="https://oddmargame.com/" rel="nofollow"><img alt="Icon overlay.png" src="https://images.pcgamingwiki.com/0/04/Icon_overlay.png" decoding="async" width="24" height="24" data-file-width="24" data-file-height="24"></a></div><div title="Oddmar on HowLongToBeat" class="template-infobox-icon svg-icon infobox-hltb"><a href="https://howlongtobeat.com/game?id=56633" title="Oddmar on HowLongToBeat" rel="nofollow"><img alt="Oddmar on HowLongToBeat" src="https://images.pcgamingwiki.com/0/04/Icon_overlay.png" decoding="async" width="24" height="24" data-file-width="24" data-file-height="24"></a></div><div title="Oddmar on IGDB" class="template-infobox-icon svg-icon infobox-igdb"><a href="https://www.igdb.com/games/oddmar" title="Oddmar on IGDB" rel="nofollow"><img alt="Oddmar on IGDB" src="https://images.pcgamingwiki.com/0/04/Icon_overlay.png" decoding="async" width="24" height="24" data-file-width="24" data-file-height="24"></a></div><div title="Oddmar on MobyGames" class="template-infobox-icon svg-icon infobox-mobygames"><a href="https://www.mobygames.com/game/oddmar" title="Oddmar on MobyGames" rel="nofollow"><img alt="Oddmar on MobyGames" src="https://images.pcgamingwiki.com/0/04/Icon_overlay.png" decoding="async" width="24" height="24" data-file-width="24" data-file-height="24"></a></div></td>
    </tr></tbody></table>';

        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::response([
                'parse' => [
                    'title' => 'Oddmar',
                    'pageid' => 999,
                    'text' => $html,
                ],
            ], 200),
        ]);

        $payload = [
            'title' => 'Oddmar',
            'pcgw_url' => 'https://www.pcgamingwiki.com/wiki/Oddmar',
            // provide required fields to allow creation
            'developers' => 'Mobge Games',
            'publishers' => 'Mobge Games',
            'release_date' => 'February 19, 2020',
            'cover_url' => 'https://thumbnails.pcgamingwiki.com/2/25/Oddmar_cover.jpg/300px-Oddmar_cover.jpg',
        ];

        (new SaveGameDataJob($payload))->handle();

        $game = Game::query()->where('title', 'Oddmar')->first();
        $this->assertNotNull($game);

        $rows = DB::table('pcgw_game_links')->where('game_id', $game->id)->get();
        $this->assertGreaterThanOrEqual(4, $rows->count());

        $this->assertDatabaseHas('pcgw_game_links', [
            'game_id' => $game->id,
            'site' => 'official-site',
            'url' => 'https://oddmargame.com/',
        ]);
        $this->assertDatabaseHas('pcgw_game_links', [
            'game_id' => $game->id,
            'site' => 'hltb',
            'url' => 'https://howlongtobeat.com/game?id=56633',
        ]);
        $this->assertDatabaseHas('pcgw_game_links', [
            'game_id' => $game->id,
            'site' => 'igdb',
            'url' => 'https://www.igdb.com/games/oddmar',
        ]);
        $this->assertDatabaseHas('pcgw_game_links', [
            'game_id' => $game->id,
            'site' => 'mobygames',
            'url' => 'https://www.mobygames.com/game/oddmar',
        ]);
    }
}
