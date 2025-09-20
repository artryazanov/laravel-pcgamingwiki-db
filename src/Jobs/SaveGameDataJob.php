<?php

namespace Artryazanov\PCGamingWiki\Jobs;

use Artryazanov\PCGamingWiki\Models\Company;
use Artryazanov\PCGamingWiki\Models\Game;
use Artryazanov\PCGamingWiki\Models\Wikipage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

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

            $wikipageId = $wikipage->id;
        }

        // If we couldn't determine a central page, we cannot persist a game row without legacy keys
        if (! $wikipageId) {
            return;
        }

        // 2) Compute normalized helpers
        $cleanTitle = $this->makeCleanTitle($title);
        $releaseDate = $this->data['release_date'] ?? null;
        $releaseYear = $this->extractYear($releaseDate);

        // 3) Upsert game row using central wikipage_id as the canonical key
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

        // 4) Map developers/publishers into normalized companies pivots
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
}
