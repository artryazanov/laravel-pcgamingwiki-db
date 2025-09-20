<?php

namespace Artryazanov\PCGamingWiki\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchGamesBatchJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $limit;
    public ?string $apcontinue;

    /**
     * Create a new job instance.
     */
    public function __construct(int $limit, ?string $apcontinue = null)
    {
        $this->limit = max(1, $limit);
        $this->apcontinue = $apcontinue ?: null;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiUrl = config('pcgamingwiki.api_url');

        Log::info('PCGamingWiki FetchGamesBatchJob: fetching', ['apcontinue' => $this->apcontinue, 'limit' => $this->limit]);

        $params = [
            'action' => 'query',
            'list' => 'allpages',
            'aplimit' => $this->limit,
            'apnamespace' => '0', // main/article namespace only
            'format' => config('pcgamingwiki.format', 'json'),
        ];
        if ($this->apcontinue) {
            $params['apcontinue'] = $this->apcontinue;
        }

        $response = Http::timeout(30)->get($apiUrl, $params);

        if (! $response->ok()) {
            Log::error('PCGamingWiki API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'apcontinue' => $this->apcontinue,
                'limit' => $this->limit,
            ]);
            return; // Fail softly; the queue can retry if configured
        }

        $data = $response->json();

        if (isset($data['error'])) {
            Log::error('PCGamingWiki API error', $data['error']);
            return;
        }

        $pages = $data['query']['allpages'] ?? [];

        if (empty($pages)) {
            Log::info('PCGamingWiki FetchGamesBatchJob: no more records to process', ['apcontinue' => $this->apcontinue]);
            return;
        }

        $dispatched = 0;
        foreach ($pages as $p) {
            $title = $p['title'] ?? null;
            $pageID = $p['pageid'] ?? null;

            // Build the canonical PCGamingWiki page URL from page title
            $pcgwUrl = null;
            if ($title) {
                $pcgwUrl = 'https://www.pcgamingwiki.com/wiki/' . rawurlencode(str_replace(' ', '_', $title));
            }

            $gameData = [
                'page_name'    => $title,
                'page_id'      => $pageID,
                'title'        => $title,
                'pcgw_url'     => $pcgwUrl,
            ];

            SaveGameDataJob::dispatch($gameData);
            $dispatched++;
        }

        Log::info('PCGamingWiki FetchGamesBatchJob: dispatched SaveGameDataJob jobs', [
            'count' => $dispatched,
            'from_apcontinue' => $this->apcontinue,
        ]);

        // Chain next batch while API provides continuation token
        $nextToken = $data['continue']['apcontinue'] ?? null;
        if ($nextToken) {
            FetchGamesBatchJob::dispatch($this->limit, $nextToken);
        }
    }
}
