<?php

namespace Tests\Jobs;

use Artryazanov\PCGamingWiki\Jobs\SaveGameDataJob;
use Artryazanov\PCGamingWiki\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaveGameDataJobWikipageContentTest extends TestCase
{
    public function test_does_not_use_wikipage_table_and_creates_game(): void
    {
        $title = 'Foo Game';
        $pcgwUrl = 'https://www.pcgamingwiki.com/wiki/Foo_Game';

        $lead = 'This is the first paragraph lead for Foo Game.';
        $html = '<div class="mw-parser-output">'
            . '<table id="infobox-game" class="vertical-navbox template-infobox"></table>'
            . '<p>' . $lead . '</p>'
            . '<p>Second paragraph</p>'
            . '</div>';

        // Fake a parse API call that returns HTML content; job should not persist Wikipage anymore
        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::sequence()
                ->push([
                    'parse' => [
                        'title' => $title,
                        'pageid' => 123,
                        'text' => $html,
                    ],
                ], 200)
                // Provide a second response but the job won't request wikitext now
                ->push([
                    'parse' => [
                        'title' => $title,
                        'pageid' => 123,
                        'wikitext' => "== Heading ==\nSome wikitext content.",
                    ],
                ], 200),
        ]);

        $payload = [
            'title' => $title,
            'pcgw_url' => $pcgwUrl,
            // Provide fields to satisfy gating and avoid cargo enrichment
            'developers' => 'Dev A',
            'publishers' => 'Pub A',
            'release_date' => '2020-01-01',
            'cover_url' => 'https://example.com/cover.jpg',
            'engines' => 'Unity',
            'modes' => 'Singleplayer',
            'genres' => 'Platform',
            'platforms' => 'Windows',
        ];

        (new SaveGameDataJob($payload))->handle();

        // Wikipage table must not exist anymore
        $this->assertFalse(Schema::hasTable('pcgw_game_wikipages'));

        // Game record should be created successfully
        $game = Game::query()->where('pcgw_url', $pcgwUrl)->first();
        $this->assertNotNull($game);
        $this->assertSame($title, $game->title);
        $this->assertSame($pcgwUrl, $game->pcgw_url);
    }
}
