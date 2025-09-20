<?php

namespace Tests\Console;

use Artryazanov\PCGamingWiki\Jobs\FetchGamesBatchJob;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SyncGamesCommandTest extends TestCase
{
    public function test_dispatches_first_batch_with_options(): void
    {
        Bus::fake();

        $this->artisan('pcgamingwiki:sync-games', [
            '--limit' => 10,
            '--apcontinue' => 'ABC',
        ])->assertSuccessful();

        Bus::assertDispatched(FetchGamesBatchJob::class, function (FetchGamesBatchJob $job) {
            return $job->limit === 10 && $job->apcontinue === 'ABC';
        });
    }

    public function test_dispatches_with_default_config_when_no_options(): void
    {
        Bus::fake();

        // Override config default for this test
        config()->set('pcgamingwiki.limit', 7);

        $this->artisan('pcgamingwiki:sync-games')
            ->assertSuccessful();

        Bus::assertDispatched(FetchGamesBatchJob::class, function (FetchGamesBatchJob $job) {
            return $job->limit === 7 && $job->apcontinue === null;
        });
    }
}
