<?php

namespace Artryazanov\PCGamingWiki\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal HTTP client for PCGamingWiki MediaWiki API used by this package.
 * Mirrors the header handling pattern from Wikipedia MediaWikiClient
 * and only exposes the methods needed here.
 */
class PCGamingWikiClient
{
    protected PendingRequest $http;

    public function __construct(
        protected string $apiEndpoint,
        protected string $userAgent,
        protected int $timeoutSeconds = 30
    ) {
        $this->http = Http::baseUrl($apiEndpoint)
            ->withHeaders(['User-Agent' => $userAgent])
            ->acceptJson()
            ->timeout($this->timeoutSeconds);
    }

    /**
     * List all pages in the main namespace using list=allpages.
     * Returns ['pages' => array<int, array{title: string, ns: int, pageid?: int}>, 'continue' => string|null] or null on failure.
     */
    public function getAllPages(int $limit = 100, ?string $continueToken = null): ?array
    {
        $limit = max(1, min(500, $limit));
        $params = [
            'action' => 'query',
            'format' => 'json',
            'list' => 'allpages',
            'aplimit' => $limit,
            'apnamespace' => 0,
        ];
        if ($continueToken) {
            $params['apcontinue'] = $continueToken;
        }

        $response = $this->http->get('', $params);
        if ($response->failed()) {
            Log::warning('PCGamingWiki getAllPages failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();
        if (isset($data['error'])) {
            Log::warning('PCGamingWiki getAllPages API error', $data['error']);

            return null;
        }

        $pages = Arr::get($data, 'query.allpages', []);
        $cont = Arr::get($data, 'continue.apcontinue');

        return [
            'pages' => array_map(function ($p) {
                return [
                    'title' => (string) ($p['title'] ?? ''),
                    'ns' => (int) ($p['ns'] ?? 0),
                    // Keep pageid if present for downstream usage
                    'pageid' => isset($p['pageid']) ? (int) $p['pageid'] : null,
                ];
            }, $pages),
            'continue' => $cont ?? null,
        ];
    }

    /**
     * Call MediaWiki parse API with given props (e.g., ['text'], ['wikitext']).
     * formatversion=2 is used for simpler JSON structure.
     */
    public function parse(array $props, string $pageTitle = null, ?int $pageId = null): ?array
    {
        $params = [
            'action' => 'parse',
            'format' => 'json',
            'formatversion' => 2,
            'prop' => implode('|', $props),
        ];
        if ($pageId) {
            $params['pageid'] = (int) $pageId;
        } elseif ($pageTitle) {
            $params['page'] = $pageTitle;
        }

        $resp = $this->http->get('', $params);
        if ($resp->failed()) {
            Log::warning('PCGamingWiki parse API failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
                'params' => $params,
            ]);

            return null;
        }

        $json = $resp->json();
        if (isset($json['error'])) {
            Log::warning('PCGamingWiki parse API error', $json['error']);

            return null;
        }

        return $json['parse'] ?? null;
    }

    /**
     * Get HTML text from parse API.
     */
    public function getInfoboxHtml(string $pageTitle, ?int $pageId = null): ?string
    {
        $parse = $this->parse(['text'], $pageTitle, $pageId);
        $text = $parse['text'] ?? null;
        if (is_array($text)) {
            $text = $text['*'] ?? null;
        }

        return is_string($text) ? $text : null;
    }

    /**
     * Get wikitext from parse API.
     */
    public function getWikitext(string $pageTitle, ?int $pageId = null): ?string
    {
        $parse = $this->parse(['wikitext'], $pageTitle, $pageId);
        $wt = $parse['wikitext'] ?? null;
        if (is_array($wt)) {
            $wt = $wt['*'] ?? null;
        }

        return is_string($wt) ? $wt : null;
    }

    /**
     * Fetch basic infobox fields via Cargo (Infobox_game table).
     * Returns standardized keys: developers, publishers, publisher, release_date, cover_url.
     */
    public function getInfoboxDataViaCargo(string $pageTitle, ?int $pageId = null): array
    {
        $fields = implode(',', [
            'Infobox_game.Developers=Developers',
            'Infobox_game.Publisher=Publisher',
            'Infobox_game.Released=Released',
            'Infobox_game.Cover_URL=Cover_URL',
        ]);

        $params = [
            'action' => 'cargoquery',
            'format' => 'json',
            'tables' => 'Infobox_game',
            'fields' => $fields,
            'limit' => 1,
        ];

        if ($pageId) {
            $params['where'] = 'Infobox_game._pageID='.(int) $pageId;
        } else {
            $quoted = '"'.str_replace('"', '""', $pageTitle).'"';
            $params['where'] = 'Infobox_game._pageName='.$quoted;
        }

        $resp = $this->http->get('', $params);
        if ($resp->failed()) {
            Log::warning('PCGW cargoquery failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
                'params' => $params,
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
            'publishers' => $titleRow['Publisher'] ?? null,
            'publisher' => $titleRow['Publisher'] ?? null,
            'release_date' => $titleRow['Released'] ?? null,
            'cover_url' => $titleRow['Cover_URL'] ?? null,
        ];
    }

    /**
     * Build canonical PCGamingWiki page URL from title.
     */
    public function buildPageUrl(?string $title): ?string
    {
        if (! $title) {
            return null;
        }

        return 'https://www.pcgamingwiki.com/wiki/' . rawurlencode(str_replace(' ', '_', $title));
    }
}
