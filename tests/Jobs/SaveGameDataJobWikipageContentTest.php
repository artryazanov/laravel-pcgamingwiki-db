<?php

namespace Tests\Jobs;

use Artryazanov\PCGamingWiki\Jobs\SaveGameDataJob;
use Artryazanov\PCGamingWiki\Models\Wikipage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaveGameDataJobWikipageContentTest extends TestCase
{
    public function test_populates_wikipage_description_and_wikitext(): void
    {
        $title = 'Foo Game';
        $pcgwUrl = 'https://www.pcgamingwiki.com/wiki/Foo_Game';

        $lead = 'This is the first paragraph lead for Foo Game.';
        $html = '<div class="mw-parser-output">'
            . '<table id="infobox-game" class="vertical-navbox template-infobox"></table>'
            . '<p>' . $lead . '</p>'
            . '<p>Second paragraph</p>'
            . '</div>';

        // Fake two sequential parse API calls: first for HTML, second for wikitext
        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::sequence()
                ->push([
                    'parse' => [
                        'title' => $title,
                        'pageid' => 123,
                        'text' => $html,
                    ],
                ], 200)
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
            // Provide fields to skip cargo enrichment and taxonomy parse branch
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

        $wikipage = Wikipage::query()->where('pcgw_url', $pcgwUrl)->first();
        $this->assertNotNull($wikipage);
        $this->assertSame($lead, $wikipage->description);
        $this->assertStringContainsString('Some wikitext content.', (string) $wikipage->wikitext);
    }
}
