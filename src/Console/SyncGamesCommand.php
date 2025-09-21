<?php

namespace Artryazanov\PCGamingWiki\Console;

use Artryazanov\PCGamingWiki\Jobs\FetchGamesBatchJob;
use Illuminate\Console\Command;

class SyncGamesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pcgamingwiki:sync-games {--limit=} {--apcontinue=}';

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
        $apcontinue = $this->option('apcontinue') ?: null;

        // Queue the first batch job; it will chain subsequent batches until no results remain
        FetchGamesBatchJob::dispatch($limit, $apcontinue);

        $this->info("Queued PCGamingWiki sync: first batch dispatched (limit={$limit}, apcontinue=".($apcontinue ?? 'null').'). Ensure a queue worker is running.');

        return self::SUCCESS;
    }
}
