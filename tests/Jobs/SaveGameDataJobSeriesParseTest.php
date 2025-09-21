<?php

namespace Tests\Jobs;

use Artryazanov\PCGamingWiki\Jobs\SaveGameDataJob;
use Artryazanov\PCGamingWiki\Models\Game;
use Artryazanov\PCGamingWiki\Models\Series;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaveGameDataJobSeriesParseTest extends TestCase
{
    public function test_parses_series_from_infobox_and_populates_pivot(): void
    {
        $title = 'Series Test Game';
        $pcgwUrl = 'https://www.pcgamingwiki.com/wiki/Series_Test_Game';

        $html = '<table class="vertical-navbox template-infobox" id="infobox-game">'
            .'<caption class="template-infobox-title">'.$title.'</caption>'
            .'<tr><th class="template-infobox-header" colspan="2">Series</th></tr>'
            .'<tr>'
            .'  <td class="template-infobox-type"></td>'
            .'  <td class="template-infobox-info"><a href="/wiki/Category:Foo_series" title="Category:Foo series">Foo Series</a></td>'
            .'</tr>'
            .'</table>';

        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::response([
                'parse' => [
                    'title' => $title,
                    'pageid' => 321,
                    'text' => $html,
                ],
            ], 200),
        ]);

        $payload = [
            'title' => $title,
            'pcgw_url' => $pcgwUrl,
            // provide required gating fields to ensure game row is created
            'developers' => 'DevCo',
            'publishers' => 'PubCo',
            'release_date' => '2020-01-01',
            'cover_url' => 'https://example.com/cover.jpg',
        ];

        (new SaveGameDataJob($payload))->handle();

        $game = Game::query()->where('pcgw_url', $pcgwUrl)->first();
        $this->assertNotNull($game);

        // Series created and pivot linked
        $this->assertTrue(Series::query()->where('name', 'Foo Series')->exists());
        $seriesId = Series::where('name', 'Foo Series')->value('id');
        $this->assertEquals(1, DB::table('pcgw_game_game_series')->where([
            'game_id' => $game->id,
            'series_id' => $seriesId,
        ])->count());
    }
}
