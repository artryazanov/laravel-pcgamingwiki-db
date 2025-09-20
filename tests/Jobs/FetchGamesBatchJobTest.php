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

        // Fake API with two entries
        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::response([
                'cargoquery' => [
                    [
                        'title' => [
                            'Page' => 'Foo Game',
                            'PageID' => 1,
                            'Developers' => 'Dev1',
                            'Publisher' => 'Pub1',
                            'Released' => '2020-01-01',
                            'Cover_URL' => 'File:FooCover.png',
                        ],
                    ],
                    [
                        'title' => [
                            'Page' => 'Bar Game',
                            'PageID' => 2,
                            'Developers' => 'Dev2',
                            'Publisher' => 'Pub2',
                            'Released' => '2019',
                            'Cover_URL' => 'https://example.com/bar.jpg',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $job = new FetchGamesBatchJob(2, 0);
        $job->handle();

        // Two SaveGameDataJob dispatched with correct payloads
        Bus::assertDispatchedTimes(SaveGameDataJob::class, 2);
        Bus::assertDispatched(SaveGameDataJob::class, function (SaveGameDataJob $job) {
            return ($job->data['title'] ?? null) === 'Foo Game';
        });
        Bus::assertDispatched(SaveGameDataJob::class, function (SaveGameDataJob $job) {
            return ($job->data['title'] ?? null) === 'Bar Game';
        });

        // Next batch dispatched with offset incremented by results count
        Bus::assertDispatched(FetchGamesBatchJob::class, function (FetchGamesBatchJob $job) {
            return $job->limit === 2 && $job->offset === 2;
        });
    }

    public function test_stops_when_no_results(): void
    {
        Bus::fake();

        Http::fake([
            'https://www.pcgamingwiki.com/w/api.php*' => Http::response([
                'cargoquery' => [],
            ], 200),
        ]);

        (new FetchGamesBatchJob(3, 9))->handle();

        Bus::assertNotDispatched(SaveGameDataJob::class);
        // Should not chain next batch
        Bus::assertNotDispatched(FetchGamesBatchJob::class, function (FetchGamesBatchJob $job) {
            return $job->offset !== 9;
        });
    }
}
