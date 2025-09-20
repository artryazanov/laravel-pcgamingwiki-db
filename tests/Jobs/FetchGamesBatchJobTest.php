<?php

namespace Tests\Jobs;

use Artryazanov\PCGamingWiki\Jobs\FetchGamesBatchJob;
use Artryazanov\PCGamingWiki\Jobs\SaveGameDataJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchGamesBatchJobTest extends TestCase
{
    public function test_dispatches_save_jobs_and_chains_next_batch_when_results_exist(): void
    {
        Bus::fake();

        // Fake API with two entries and continuation token
        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::response([
                'batchcomplete' => true,
                'continue' => [
                    'apcontinue' => '007_Legends',
                    'continue' => '-||',
                ],
                'query' => [
                    'allpages' => [
                        [
                            'pageid' => 1,
                            'ns' => 0,
                            'title' => 'Foo Game',
                        ],
                        [
                            'pageid' => 2,
                            'ns' => 0,
                            'title' => 'Bar Game',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $job = new FetchGamesBatchJob(2, null);
        $job->handle();

        // Two SaveGameDataJob dispatched with correct payloads
        Bus::assertDispatchedTimes(SaveGameDataJob::class, 2);
        Bus::assertDispatched(SaveGameDataJob::class, function (SaveGameDataJob $job) {
            return ($job->data['title'] ?? null) === 'Foo Game';
        });
        Bus::assertDispatched(SaveGameDataJob::class, function (SaveGameDataJob $job) {
            return ($job->data['title'] ?? null) === 'Bar Game';
        });

        // Next batch dispatched with same limit and apcontinue token
        Bus::assertDispatched(FetchGamesBatchJob::class, function (FetchGamesBatchJob $job) {
            return $job->limit === 2 && $job->apcontinue === '007_Legends';
        });
    }

    public function test_stops_when_no_results(): void
    {
        Bus::fake();

        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::response([
                'batchcomplete' => true,
                'query' => [
                    'allpages' => [],
                ],
            ], 200),
        ]);

        (new FetchGamesBatchJob(3, null))->handle();

        Bus::assertNotDispatched(SaveGameDataJob::class);
        // Should not chain next batch
        Bus::assertNotDispatched(FetchGamesBatchJob::class);
    }
}
