<?php

namespace Artryazanov\PCGamingWiki\Jobs;

use Artryazanov\PCGamingWiki\Models\Company;
use Artryazanov\PCGamingWiki\Models\Engine;
use Artryazanov\PCGamingWiki\Models\Genre;
use Artryazanov\PCGamingWiki\Models\Mode;
use Artryazanov\PCGamingWiki\Models\Platform;
use Artryazanov\PCGamingWiki\Models\Series;
use Artryazanov\PCGamingWiki\Models\Game;
use Artryazanov\PCGamingWiki\Models\Wikipage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SaveGameDataJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    /**
     * The game data payload from the API.
     *
     * @var array
     */
    public $data;

    /**
     * Create a new job instance.
     *
     * @param array $data  Associative array of game data.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1) Upsert central wikipage (if URL or title provided)
        $wikipageId = null;
        $title = $this->data['title'] ?? $this->data['page_name'] ?? null;
        $pcgwUrl = $this->data['pcgw_url'] ?? null;
        $html = null; // will reuse if fetched
        if ($title || $pcgwUrl) {
            $wikipage = Wikipage::query()
                ->when($pcgwUrl, fn($q) => $q->where('pcgw_url', $pcgwUrl))
                ->when(! $pcgwUrl && $title, fn($q) => $q->where('title', $title))
                ->first();

            if (! $wikipage) {
                $wikipage = new Wikipage([
                    'title' => $title,
                    'pcgw_url' => $pcgwUrl,
                ]);
                $wikipage->save();
            } else {
                // Fill missing values only
                $dirty = false;
                if ($title && ! $wikipage->title) { $wikipage->title = $title; $dirty = true; }
                if ($pcgwUrl && ! $wikipage->pcgw_url) { $wikipage->pcgw_url = $pcgwUrl; $dirty = true; }
                if ($dirty) { $wikipage->save(); }
            }

            // 1.1) Enrich wikipage description and wikitext if missing
            try {
                $pageId = $this->data['page_id'] ?? null;
                $wpDirty = false;
                if (empty($wikipage->description) || empty($wikipage->wikitext)) {
                    // Fetch HTML once; will also be reused for taxonomy parse later
                    $html = $this->fetchInfoboxHtml($title, $pageId);
                }
                if (empty($wikipage->description) && $html) {
                    $desc = $this->parseLeadFromHtml($html);
                    if ($desc) { $wikipage->description = $desc; $wpDirty = true; }
                }
                if (empty($wikipage->wikitext)) {
                    $wt = $this->fetchWikitext($title, $pageId);
                    if ($wt) { $wikipage->wikitext = $wt; $wpDirty = true; }
                }
                if ($wpDirty) { $wikipage->save(); }
            } catch (\Throwable $e) {
                Log::warning('PCGW SaveGameDataJob: failed to enrich wikipage meta', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }

            $wikipageId = $wikipage->id;
        }

        // If we couldn't determine a central page, we cannot persist a game row without legacy keys
        if (! $wikipageId) {
            return;
        }

        // 1.5) Enrich payload from PCGamingWiki if key fields are missing (Cargo)
        $needsDevelopers = empty($this->data['developers'] ?? null);
        $needsPublishers = empty($this->data['publishers'] ?? null) && empty($this->data['publisher'] ?? null);
        $needsRelease = empty($this->data['release_date'] ?? null);
        $needsCover = empty($this->data['cover_url'] ?? null);
        if ($title && ($needsDevelopers || $needsPublishers || $needsRelease || $needsCover)) {
            try {
                $pageId = $this->data['page_id'] ?? null;
                $fetched = $this->fetchInfoboxData($title, $pageId);
                // Merge only missing keys
                foreach ([
                    'developers' => 'developers',
                    'publishers' => 'publishers',
                    'release_date' => 'release_date',
                    'cover_url' => 'cover_url',
                ] as $src => $dst) {
                    if (($this->data[$dst] ?? null) === null && ($fetched[$src] ?? null) !== null) {
                        $this->data[$dst] = $fetched[$src];
                    }
                }
                // Backward compatibility: some sources might provide 'publisher' singular
                if (($this->data['publishers'] ?? null) === null && ($fetched['publisher'] ?? null) !== null) {
                    $this->data['publishers'] = $fetched['publisher'];
                }
            } catch (\Throwable $e) {
                Log::warning('PCGW SaveGameDataJob: enrichment failed', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 1.6) Enrich from HTML infobox before deciding to create a game row
        $needsEngines   = empty($this->data['engines'] ?? null);
        $needsModes     = empty($this->data['modes'] ?? null);
        $needsGenres    = empty($this->data['genres'] ?? null);
        $needsPlatforms = empty($this->data['platforms'] ?? null);
        $needsSeries    = empty($this->data['series'] ?? null);
        if ($title && ($needsEngines || $needsModes || $needsGenres || $needsPlatforms || $needsSeries || $needsRelease || $needsCover || $needsDevelopers || $needsPublishers)) {
            try {
                $pageId = $this->data['page_id'] ?? null;
                if ($html === null) { $html = $this->fetchInfoboxHtml($title, $pageId); }
                if ($html) {
                    $parsed = $this->parseInfoboxTaxonomies($html);
                    foreach (['developers','publishers','engines','modes','genres','platforms','series'] as $key) {
                        if (empty($this->data[$key] ?? null) && !empty($parsed[$key] ?? [])) {
                            $this->data[$key] = implode('; ', $parsed[$key]);
                        }
                    }
                    // Fallbacks from HTML for release date and cover image
                    if (empty($this->data['release_date'] ?? null) && !empty($parsed['release_dates'] ?? [])) {
                        $this->data['release_date'] = $parsed['release_dates'][0];
                    }
                    if (empty($this->data['cover_url'] ?? null) && !empty($parsed['cover_url'] ?? null)) {
                        $this->data['cover_url'] = $parsed['cover_url'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('PCGW SaveGameDataJob: infobox HTML parse failed', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 2) Gate: only add to pcgw_games when release_date and (developers or publishers) exist
        $hasRelease = !empty($this->data['release_date'] ?? null);
        $hasDevOrPub = count($this->parseNames($this->data['developers'] ?? '')) > 0 || count($this->parseNames($this->data['publishers'] ?? '')) > 0;
        if (! $hasRelease || ! $hasDevOrPub) {
            Log::info('PCGW SaveGameDataJob: skipping game creation due to missing required fields', [
                'title' => $title,
                'has_release' => $hasRelease,
                'has_companies' => $hasDevOrPub,
            ]);
            return;
        }

        // 3) Compute normalized helpers and upsert game row
        $cleanTitle = $this->makeCleanTitle($title);
        $releaseDate = $this->data['release_date'] ?? null;
        $releaseYear = $this->extractYear($releaseDate);

        $game = Game::updateOrCreate(
            ['wikipage_id' => $wikipageId],
            [
                'clean_title'   => $cleanTitle,
                'release_date'  => $releaseDate,
                'release_year'  => $releaseYear,
                'cover_url'     => $this->normalizeCoverUrl($this->data['cover_url'] ?? null),
                'wikipage_id'   => $wikipageId,
            ]
        );

        // 4) Persist companies from developers/publishers (single idempotent pass)
        $developerNames = $this->parseNames($this->data['developers'] ?? '');
        $publisherNames = $this->parseNames($this->data['publishers'] ?? '');
        foreach ($developerNames as $name) {
            $company = Company::firstOrCreate(['name' => $name]);
            $this->upsertCompanyPivot($game->id, $company->id, 'developer');
        }
        foreach ($publisherNames as $name) {
            $company = Company::firstOrCreate(['name' => $name]);
            $this->upsertCompanyPivot($game->id, $company->id, 'publisher');
        }

        // 5) Persist taxonomy: engines, modes, genres, platforms, series
        $engineNames = $this->parseNames($this->data['engines'] ?? '');
        foreach ($engineNames as $name) {
            $engine = Engine::firstOrCreate(['name' => $name]);
            $this->upsertPivot('pcgw_game_game_engine', [
                'game_id' => $game->id,
                'engine_id' => $engine->id,
            ]);
        }

        $modeNames = $this->parseNames($this->data['modes'] ?? '');
        foreach ($modeNames as $name) {
            $mode = Mode::firstOrCreate(['name' => $name]);
            $this->upsertPivot('pcgw_game_game_mode', [
                'game_id' => $game->id,
                'mode_id' => $mode->id,
            ]);
        }

        $genreNames = $this->parseNames($this->data['genres'] ?? '');
        foreach ($genreNames as $name) {
            $genre = Genre::firstOrCreate(['name' => $name]);
            $this->upsertPivot('pcgw_game_game_genre', [
                'game_id' => $game->id,
                'genre_id' => $genre->id,
            ]);
        }

        $platformNames = $this->parseNames($this->data['platforms'] ?? '');
        foreach ($platformNames as $name) {
            $platform = Platform::firstOrCreate(['name' => $name]);
            $this->upsertPivot('pcgw_game_game_platform', [
                'game_id' => $game->id,
                'platform_id' => $platform->id,
            ]);
        }

        $seriesNames = $this->parseNames($this->data['series'] ?? '');
        foreach ($seriesNames as $name) {
            $series = Series::firstOrCreate(['name' => $name]);
            $this->upsertPivot('pcgw_game_game_series', [
                'game_id' => $game->id,
                'series_id' => $series->id,
            ]);
        }
    }

    /**
     * Normalize cover image value to a usable URL when possible.
     * If a direct URL is provided, return it. If we get a File:Name, build a Special:FilePath URL.
     */
    protected function normalizeCoverUrl(?string $cover): ?string
    {
        if (! $cover) {
            return null;
        }

        // Already a URL
        if (str_starts_with($cover, 'http://') || str_starts_with($cover, 'https://')) {
            return rawurldecode($cover);
        }

        // If the value looks like a MediaWiki file title, build a Special:FilePath link
        if (str_starts_with($cover, 'File:') || str_starts_with($cover, 'Image:')) {
            $encoded = rawurlencode($cover);
            return 'https://www.pcgamingwiki.com/wiki/Special:FilePath/' . $encoded;
        }

        // Otherwise, return as-is
        return $cover;
    }

    /**
     * Build a cleaner title by stripping disambiguation parentheses and trimming.
     */
    protected function makeCleanTitle(?string $title): ?string
    {
        if (! $title) {
            return null;
        }
        // Remove content in parentheses and trailing/leading whitespace
        $clean = preg_replace('/\s*\([^)]*\)\s*/', ' ', $title) ?? $title;
        $clean = trim(preg_replace('/\s+/', ' ', $clean));
        return $clean !== '' ? $clean : null;
    }

    /**
     * Extract first 4-digit year from a date or free text string.
     */
    protected function extractYear(?string $dateText): ?int
    {
        if (! $dateText) {
            return null;
        }
        if (preg_match('/(19|20)\d{2}/', $dateText, $m)) {
            return (int) $m[0];
        }
        return null;
    }

    /**
     * Parse semicolon/comma/pipe separated lists into unique, trimmed names.
     */
    protected function parseNames(?string $list): array
    {
        if (! $list) {
            return [];
        }
        // Normalize delimiters to semicolons
        $normalized = str_replace(['|', '/', '\\', ' and '], ';', $list);
        $parts = preg_split('/[;,]+/', $normalized) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $name = trim($part);
            // Remove surrounding wiki markup like [[Name]]
            if ($name !== '') {
                $name = preg_replace('/^\[\[|\]\]$/', '', $name);
                $names[] = $name;
            }
        }
        // Deduplicate, preserve order
        $names = array_values(array_unique($names));
        return $names;
    }

    /**
     * Idempotently insert company pivot with role.
     */
    protected function upsertCompanyPivot(int $gameId, int $companyId, string $role): void
    {
        DB::table('pcgw_game_game_company')->updateOrInsert([
            'game_id' => $gameId,
            'company_id' => $companyId,
            'role' => $role,
        ], []);
    }

    /**
     * Fetch infobox data for a PCGamingWiki page using Cargo.
     * Returns standardized keys: developers, publishers, release_date, cover_url.
     */
    protected function fetchInfoboxData(string $pageTitle, ?int $pageId = null): array
    {
        $apiUrl = config('pcgamingwiki.api_url', 'https://www.pcgamingwiki.com/w/api.php');

        // Prepare fields with aliases so response keys are stable
        $fields = implode(',', [
            'Infobox_game.Developers=Developers',
            'Infobox_game.Publisher=Publisher',
            'Infobox_game.Released=Released',
            'Infobox_game.Cover_URL=Cover_URL',
        ]);

        $params = [
            'action' => 'cargoquery',
            'tables' => 'Infobox_game',
            'fields' => $fields,
            'limit' => 1,
            'format' => config('pcgamingwiki.format', 'json'),
        ];

        // Use page ID if available, fallback to exact page name
        if ($pageId) {
            $params['where'] = 'Infobox_game._pageID=' . ((int) $pageId);
        } else {
            // Quote the title for Cargo where clause; escape existing quotes by doubling
            $quoted = '"' . str_replace('"', '""', $pageTitle) . '"';
            $params['where'] = 'Infobox_game._pageName=' . $quoted;
        }

        $resp = Http::timeout(30)->get($apiUrl, $params);
        if (! $resp->ok()) {
            Log::warning('PCGW cargoquery failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
                'title' => $pageTitle,
                'page_id' => $pageId,
            ]);
            return [];
        }

        $json = $resp->json();
        $rows = $json['cargoquery'] ?? [];
        if (empty($rows)) {
            return [];
        }
        $titleRow = $rows[0]['title'] ?? [];

        return [
            'developers' => $titleRow['Developers'] ?? null,
            // normalize singular to our plural key
            'publishers' => $titleRow['Publisher'] ?? null,
            'publisher' => $titleRow['Publisher'] ?? null,
            'release_date' => $titleRow['Released'] ?? null,
            'cover_url' => $titleRow['Cover_URL'] ?? null,
        ];
    }

    /**
     * Fetch infobox HTML via MediaWiki parse API.
     */
    protected function fetchInfoboxHtml(string $pageTitle, ?int $pageId = null): ?string
    {
        $apiUrl = config('pcgamingwiki.api_url', 'https://www.pcgamingwiki.com/w/api.php');

        $params = [
            'action' => 'parse',
            'prop' => 'text',
            'format' => config('pcgamingwiki.format', 'json'),
            'formatversion' => 2,
        ];
        if ($pageId) {
            $params['pageid'] = (int) $pageId;
        } else {
            $params['page'] = $pageTitle;
        }

        $resp = Http::timeout(30)->get($apiUrl, $params);
        if (! $resp->ok()) {
            Log::warning('PCGW parse API failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
                'title' => $pageTitle,
                'page_id' => $pageId,
            ]);
            return null;
        }

        $json = $resp->json();
        // formatversion=2 returns parse.text as string; otherwise it's array['*']
        $text = $json['parse']['text'] ?? null;
        if (is_array($text)) {
            $text = $text['*'] ?? null;
        }
        return is_string($text) ? $text : null;
    }

    /**
     * Fetch raw page wikitext via MediaWiki parse API.
     */
    protected function fetchWikitext(string $pageTitle, ?int $pageId = null): ?string
    {
        $apiUrl = config('pcgamingwiki.api_url', 'https://www.pcgamingwiki.com/w/api.php');

        $params = [
            'action' => 'parse',
            'prop' => 'wikitext',
            'format' => config('pcgamingwiki.format', 'json'),
            'formatversion' => 2,
        ];
        if ($pageId) {
            $params['pageid'] = (int) $pageId;
        } else {
            $params['page'] = $pageTitle;
        }

        $resp = Http::timeout(30)->get($apiUrl, $params);
        if (! $resp->ok()) {
            Log::warning('PCGW parse API (wikitext) failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
                'title' => $pageTitle,
                'page_id' => $pageId,
            ]);
            return null;
        }

        $json = $resp->json();
        $wt = $json['parse']['wikitext'] ?? null;
        if (is_array($wt)) {
            $wt = $wt['*'] ?? null;
        }
        return is_string($wt) ? $wt : null;
    }

    /**
     * Extract lead paragraph (plain text) from full page HTML.
     */
    protected function parseLeadFromHtml(string $html): ?string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        if (! $loaded) {
            return null;
        }
        $xpath = new \DOMXPath($dom);
        $p = $xpath->query('//div[contains(@class, "mw-parser-output")]/p[normalize-space()][1]')->item(0);
        if (! $p) {
            $p = $xpath->query('//p[normalize-space()][1]')->item(0);
        }
        if ($p) {
            $text = $this->normalizeText($p->textContent);
            return $text !== '' ? $text : null;
        }
        return null;
    }

    /**
     * Parse infobox HTML to extract taxonomies.
     * Returns ['developers'=>[], 'publishers'=>[], 'engines'=>[], 'modes'=>[], 'genres'=>[], 'platforms'=>[], 'series'=>[]]
     */
    protected function parseInfoboxTaxonomies(string $html): array
    {
        $result = [
            'developers' => [], 'publishers' => [], 'engines' => [], 'modes' => [], 'genres' => [], 'platforms' => [], 'series' => [],
            'release_dates' => [], 'cover_url' => null,
        ];

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        if (! $loaded) {
            return $result;
        }
        $xpath = new \DOMXPath($dom);
        $table = $xpath->query('//table[@id="infobox-game"]')->item(0);
        if (! $table) {
            return $result;
        }

        $currentHeader = null;
        foreach ($xpath->query('.//tr', $table) as $tr) {
            /** @var \DOMElement $tr */

            // Cover image extraction
            if ($result['cover_url'] === null) {
                $img = $xpath->query('.//td[contains(@class, "template-infobox-cover")]//img', $tr)->item(0);
                if ($img && $img->hasAttribute('src')) {
                    $result['cover_url'] = $this->normalizeText($img->getAttribute('src')) ?: null;
                }
            }

            $th = $xpath->query('.//th[contains(@class, "template-infobox-header")]', $tr)->item(0);
            if ($th) {
                $currentHeader = $this->normalizeText($th->textContent);
                continue;
            }

            // Platform rows live under "Release dates" header: first TD is platform name; info TD holds date string
            if ($currentHeader && stripos($currentHeader, 'Release dates') !== false) {
                $typeTd = $xpath->query('.//td[contains(@class, "template-infobox-type")]', $tr)->item(0);
                if ($typeTd) {
                    $platform = $this->normalizeText($typeTd->textContent);
                    if ($platform !== '') { $result['platforms'][] = $platform; }
                }
                $dateTd = $xpath->query('.//td[contains(@class, "template-infobox-info")]', $tr)->item(0);
                if ($dateTd) {
                    $dateText = $this->normalizeText($dateTd->textContent);
                    if ($dateText !== '') { $result['release_dates'][] = $dateText; }
                }
            }

            // General info rows: label may be implicit via header, value is in .template-infobox-info
            $infoTd = $xpath->query('.//td[contains(@class, "template-infobox-info")]', $tr)->item(0);
            if (! $infoTd) { continue; }

            $texts = [];
            foreach ($xpath->query('.//a', $infoTd) as $a) {
                $txt = $this->normalizeText($a->textContent);
                if ($txt !== '') { $texts[] = $txt; }
            }
            if (empty($texts)) {
                $raw = $this->normalizeText($infoTd->textContent);
                if ($raw !== '') {
                    // split by comma
                    foreach (preg_split('/\s*,\s*/', $raw) as $p) {
                        $p = $this->normalizeText($p);
                        if ($p !== '') { $texts[] = $p; }
                    }
                }
            }

            if (! $currentHeader) { continue; }
            $header = strtolower($this->normalizeText($currentHeader));

            if (strpos($header, 'developer') !== false) {
                $result['developers'] = array_merge($result['developers'], $texts);
            } elseif (strpos($header, 'publisher') !== false) {
                $result['publishers'] = array_merge($result['publishers'], $texts);
            } elseif (strpos($header, 'engine') !== false) {
                $result['engines'] = array_merge($result['engines'], $texts);
            } elseif (strpos($header, 'series') !== false) {
                $result['series'] = array_merge($result['series'], $texts);
            } elseif (strpos($header, 'taxonomy') !== false) {
                // Under Taxonomy, the first TD is the label like Modes/Genres
                $labelTd = $xpath->query('.//td[contains(@class, "template-infobox-type")]', $tr)->item(0);
                if ($labelTd) {
                    $label = strtolower($this->normalizeText($labelTd->textContent));
                    if (strpos($label, 'mode') !== false) {
                        $result['modes'] = array_merge($result['modes'], $texts);
                    } elseif (strpos($label, 'genre') !== false) {
                        $result['genres'] = array_merge($result['genres'], $texts);
                    }
                }
            }
        }

        // Deduplicate and normalize whitespace for list fields
        foreach (['developers','publishers','engines','modes','genres','platforms','series','release_dates'] as $k) {
            $arr = $result[$k];
            $norm = [];
            foreach ($arr as $v) {
                $v = $this->normalizeText($v);
                if ($v !== '') { $norm[] = $v; }
            }
            $result[$k] = array_values(array_unique($norm));
        }

        // Cover URL already normalized via normalizeText
        if ($result['cover_url'] !== null && $result['cover_url'] === '') {
            $result['cover_url'] = null;
        }

        return $result;
    }

    /**
     * Normalize whitespace in extracted text: convert NBSP to regular spaces, collapse whitespace, trim.
     */
    protected function normalizeText(?string $text): string
    {
        if ($text === null) { return ''; }
        // Decode any HTML entities just in case
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Replace non-breaking spaces with regular spaces
        $text = str_replace("\xC2\xA0", ' ', $text);
        // Collapse any whitespace to single spaces (unicode-aware)
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * Generic pivot upsert helper for simple two-column pivot tables.
     */
    protected function upsertPivot(string $pivotTable, array $keys): void
    {
        DB::table($pivotTable)->updateOrInsert($keys, []);
    }
}
