<?php

namespace Artryazanov\PCGamingWiki\Jobs;

use Artryazanov\PCGamingWiki\Services\PCGamingWikiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class FetchGamesBatchJob extends AbstractPCGamingWikiJob implements ShouldQueue
{
    // Traits are provided by the abstract base

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
        /** @var PCGamingWikiClient $client */
        $client = app(PCGamingWikiClient::class);

        Log::info('PCGamingWiki FetchGamesBatchJob: fetching', ['apcontinue' => $this->apcontinue, 'limit' => $this->limit]);

        $result = $client->getAllPages($this->limit, $this->apcontinue);
        if ($result === null) {
            Log::error('PCGamingWiki API request failed', [
                'apcontinue' => $this->apcontinue,
                'limit' => $this->limit,
            ]);

            return; // Fail softly; the queue can retry if configured
        }

        $pages = $result['pages'] ?? [];

        if (empty($pages)) {
            Log::info('PCGamingWiki FetchGamesBatchJob: no more records to process', ['apcontinue' => $this->apcontinue]);

            return;
        }

        $dispatched = 0;
        foreach ($pages as $p) {
            $title = $p['title'] ?? null;
            $pageID = $p['pageid'] ?? null;

            // Build the canonical PCGamingWiki page URL from page title via client helper
            $pcgwUrl = $client->buildPageUrl($title);

            $gameData = [
                'page_name' => $title,
                'page_id' => $pageID,
                'title' => $title,
                'pcgw_url' => $pcgwUrl,
            ];

            SaveGameDataJob::dispatch($gameData);
            $dispatched++;
        }

        Log::info('PCGamingWiki FetchGamesBatchJob: dispatched SaveGameDataJob jobs', [
            'count' => $dispatched,
            'from_apcontinue' => $this->apcontinue,
        ]);

        // Chain next batch while API provides continuation token
        $nextToken = $result['continue'] ?? null;
        if ($nextToken) {
            FetchGamesBatchJob::dispatch($this->limit, $nextToken);
        }
    }
}
