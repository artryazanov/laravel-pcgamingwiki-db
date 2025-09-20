<?php

namespace Artryazanov\PCGamingWiki\Console\Commands;

use Illuminate\Console\Command;
use Artryazanov\PCGamingWiki\Jobs\FetchGamesBatchJob;

class SyncGamesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pcgamingwiki:sync-games {--limit=} {--offset=}';

    /**
     * The console command description.
     */
    protected $description = 'Sync games and their infobox data from PCGamingWiki';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $defaultLimit = (int) config('pcgamingwiki.limit');
        $limit = (int) ($this->option('limit') ?: $defaultLimit);
        $offset = (int) ($this->option('offset') ?: 0);

        // Queue the first batch job; it will chain subsequent batches until no results remain
        FetchGamesBatchJob::dispatch($limit, $offset);

        $this->info("Queued PCGamingWiki sync: first batch dispatched (limit={$limit}, offset={$offset}). Ensure a queue worker is running.");

        return self::SUCCESS;
    }
}
