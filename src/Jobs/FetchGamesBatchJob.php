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
    public int $offset;

    /**
     * Create a new job instance.
     */
    public function __construct(int $limit, int $offset = 0)
    {
        $this->limit = max(1, $limit);
        $this->offset = max(0, $offset);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $apiUrl = config('pcgamingwiki.api_url');

        // Cargo table and fields to request (keep in sync with command expectations)
        $fields = implode(',', [
            'Infobox_game._pageName=Page',
            'Infobox_game._pageID=PageID',
            'Infobox_game.Developers',
            'Infobox_game.Publisher',
            'Infobox_game.Released',
            'Infobox_game.Cover_URL',
        ]);

        Log::info('PCGamingWiki FetchGamesBatchJob: fetching', ['offset' => $this->offset, 'limit' => $this->limit]);

        $response = Http::timeout(30)->get($apiUrl, [
            'action' => 'cargoquery',
            'tables' => 'Infobox_game',
            'fields' => $fields,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'format' => config('pcgamingwiki.format', 'json'),
        ]);

        if (! $response->ok()) {
            Log::error('PCGamingWiki API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'offset' => $this->offset,
                'limit' => $this->limit,
            ]);
            return; // Fail softly; the queue can retry if configured
        }

        $data = $response->json();

        if (isset($data['error'])) {
            Log::error('PCGamingWiki API error', $data['error']);
            return;
        }

        $results = $data['cargoquery'] ?? [];

        if (empty($results)) {
            Log::info('PCGamingWiki FetchGamesBatchJob: no more records to process', ['offset' => $this->offset]);
            return;
        }

        $dispatched = 0;
        foreach ($results as $entry) {
            $titleBlock = $entry['title'] ?? [];
            $rowFields = $entry['title'] ?? [];

            $pageName = $titleBlock['Page'] ?? null;
            $pageID = $titleBlock['PageID'] ?? null;

            // Build the canonical PCGamingWiki page URL from page title
            $pcgwUrl = null;
            if ($pageName) {
                $pcgwUrl = 'https://www.pcgamingwiki.com/wiki/' . rawurlencode(str_replace(' ', '_', $pageName));
            }

            $gameData = [
                'page_name'    => $pageName,
                'page_id'      => $pageID,
                'title'        => $pageName,
                'developers'   => $rowFields['Developers'] ?? null,
                'publishers'   => $rowFields['Publisher'] ?? null,
                'release_date' => $rowFields['Released'] ?? null,
                'cover_url'    => $rowFields['Cover_URL'] ?? null,
                'pcgw_url'     => $pcgwUrl,
            ];

            SaveGameDataJob::dispatch($gameData);
            $dispatched++;
        }

        Log::info('PCGamingWiki FetchGamesBatchJob: dispatched SaveGameDataJob jobs', [
            'count' => $dispatched,
            'from_offset' => $this->offset,
        ]);

        // Chain next batch while there are results
        $nextOffset = $this->offset + count($results);
        FetchGamesBatchJob::dispatch($this->limit, $nextOffset);
    }
}
