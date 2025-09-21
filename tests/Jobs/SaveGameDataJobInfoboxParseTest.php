<?php

namespace Tests\Jobs;

use Artryazanov\PCGamingWiki\Jobs\SaveGameDataJob;
use Artryazanov\PCGamingWiki\Models\Engine;
use Artryazanov\PCGamingWiki\Models\Game;
use Artryazanov\PCGamingWiki\Models\Genre;
use Artryazanov\PCGamingWiki\Models\Mode;
use Artryazanov\PCGamingWiki\Models\Platform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaveGameDataJobInfoboxParseTest extends TestCase
{
    public function test_parses_infobox_html_and_populates_taxonomies(): void
    {
        // Fake parse API to return sample infobox HTML
        $html = '<table class="vertical-navbox template-infobox" id="infobox-game">
    <caption class="template-infobox-title">Oddmar</caption>
<tbody><tr>
        <td class="template-infobox-cover" colspan="2"><a href="/wiki/File:Oddmar_cover.jpg" class="image"><img alt="Oddmar cover" src="https://thumbnails.pcgamingwiki.com/2/25/Oddmar_cover.jpg/300px-Oddmar_cover.jpg" decoding="async" width="300" height="300" srcset="https://thumbnails.pcgamingwiki.com/2/25/Oddmar_cover.jpg/450px-Oddmar_cover.jpg 1.5x, https://thumbnails.pcgamingwiki.com/2/25/Oddmar_cover.jpg/600px-Oddmar_cover.jpg 2x" data-file-width="800" data-file-height="800"></a></td>
    </tr>

<tr>
        <th class="template-infobox-header" colspan="2">Developers</th>
    </tr>
    <tr>
    <td class="template-infobox-type"></td>
    <td class="template-infobox-info"><a href="/wiki/Company:Mobge_Games" title="Company:Mobge Games"></a><a href="/wiki/Company:Mobge_Games" title="Company:Mobge Games">Mobge Games</a></td>
</tr>
<tr>
    <td class="template-infobox-type"></td>
    <td class="template-infobox-info"><a href="/wiki/Company:Senri" title="Company:Senri"></a><a href="/wiki/Company:Senri" title="Company:Senri">Senri</a></td>
</tr>
<tr>
        <th class="template-infobox-header" colspan="2">Publishers</th>
    </tr>
    <tr>
    <td class="template-infobox-type"></td>
    <td class="template-infobox-info"><a href="/wiki/Company:Mobge_Games" title="Company:Mobge Games"></a><a href="/wiki/Company:Mobge_Games" title="Company:Mobge Games">Mobge Games</a></td>
</tr>
<tr>
        <th class="template-infobox-header" colspan="2">Engines</th>
    </tr>
    <tr>
    <td class="template-infobox-type"></td>
    <td class="template-infobox-info"><a href="/wiki/Engine:Unity" title="Engine:Unity"></a><a href="/wiki/Engine:Unity" title="Engine:Unity">Unity</a></td>
</tr>
<tr>
        <th class="template-infobox-header" colspan="2">Release dates</th>
    </tr>
    <tr>
    <td class="template-infobox-type">macOS&nbsp;(OS&nbsp;X)</td>
    <td class="template-infobox-info">February 19, 2020</td>
</tr><tr>
        <th class="template-infobox-header" colspan="2">Reception</th>
    </tr>
    <tr>
            <td class="template-infobox-type">OpenCritic</td>
            <td class="template-infobox-info"><a rel="nofollow" class="external text" href="https://opencritic.com/game/9082/oddmar">79</a></td>
        </tr>
<tr>
        <th class="template-infobox-header" colspan="2">Taxonomy</th>
    </tr>
    <tr>
  <td class="template-infobox-type">Monetization</td>
  <td class="template-infobox-info"><a href="/wiki/Category:Subscription_gaming_service" title="Category:Subscription gaming service"><abbr title="Game is included in a collection of games accessible as part of a monthly video game subscription service such as EA Play or Xbox Game Pass.">Subscription gaming service</abbr></a></td>
</tr>


<tr>
            <td class="template-infobox-type">Modes</td>
            <td class="template-infobox-info"><a href="/wiki/Category:Singleplayer" title="Category:Singleplayer"><abbr title="The game supports solo play through a singleplayer mode.">Singleplayer</abbr></a></td>
        </tr>

<tr>
            <td class="template-infobox-type">Perspectives</td>
            <td class="template-infobox-info"><a href="/wiki/Category:Side_view" title="Category:Side view"><abbr title="Any view from the side for both scrolling and static environments.">Side view</abbr></a>, <a href="/wiki/Category:Scrolling" title="Category:Scrolling"><abbr title="Game world scrolls according to movement of the character.">Scrolling</abbr></a></td>
        </tr>
<tr>
  <td class="template-infobox-type">Controls</td>
  <td class="template-infobox-info"><a href="/wiki/Category:Direct_control" title="Category:Direct control"><abbr title="Directly control a single character at a time, usually using directional buttons and other action buttons to interact with the environment directly.">Direct control</abbr></a></td>
</tr>
<tr>
            <td class="template-infobox-type">Genres</td>
            <td class="template-infobox-info"><a href="/wiki/Category:Platform" title="Category:Platform"><abbr title="Platform games (also referred to as&quot;platformers&quot;) can be both 2D and 3D games in which jumping or climbing onto platforms on various elevations is a major focus of the game. Early platform games mostly focused on climbing onto platforms using ladders, while later games generally focus more on jumping.">Platform</abbr></a>, <a href="/wiki/Category:Puzzle" title="Category:Puzzle"><abbr title="Puzzle solving gameplay, which could include physical, logical, trivia, word puzzles and others etc.">Puzzle</abbr></a></td>
        </tr>


<tr>
            <td class="template-infobox-type">Art styles</td>
            <td class="template-infobox-info"><a href="/wiki/Category:Cartoon" title="Category:Cartoon"><abbr title="Exaggerated art styles based primarily on Western animated films and TV shows, with non-realistic character body shapes and proportions, colorful, larger-than-life environments, and sometimes a disregard of the laws of physics. Often runs on the rule of fun. Not to be confused with anime art styles.">Cartoon</abbr></a></td>
        </tr>
<tr>
  <td class="template-infobox-type">Themes</td>
  <td class="template-infobox-info"><a href="/wiki/Category:Fantasy" title="Category:Fantasy"><abbr title="Settings that are inspired by fairytales, revolve strongly around magic, include fantastic creatures, or make use of old myths.">Fantasy</abbr></a></td>
</tr><tr>
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
            // provide these to skip cargo enrichment branch
            'developers' => 'Mobge Games; Senri',
            'publishers' => 'Mobge Games',
            'release_date' => 'February 19, 2020',
            'cover_url' => 'https://thumbnails.pcgamingwiki.com/2/25/Oddmar_cover.jpg/300px-Oddmar_cover.jpg',
        ];

        (new SaveGameDataJob($payload))->handle();

        $game = Game::query()->where('title', 'Oddmar')->first();
        $this->assertNotNull($game);

        // Engines
        $this->assertTrue(Engine::query()->where('name', 'Unity')->exists());
        $this->assertEquals(1, DB::table('pcgw_game_game_engine')->where('game_id', $game->id)->count());

        // Modes
        $this->assertTrue(Mode::query()->where('name', 'Singleplayer')->exists());
        $this->assertEquals(1, DB::table('pcgw_game_game_mode')->where('game_id', $game->id)->count());

        // Genres
        $this->assertTrue(Genre::query()->where('name', 'Platform')->exists());
        $this->assertTrue(Genre::query()->where('name', 'Puzzle')->exists());
        $this->assertEquals(2, DB::table('pcgw_game_game_genre')->where('game_id', $game->id)->count());

        // Platforms (from Release dates rows)
        $this->assertTrue(Platform::query()->where('name', 'macOS (OS X)')->exists());
        $this->assertEquals(1, DB::table('pcgw_game_game_platform')->where('game_id', $game->id)->count());
    }
}
