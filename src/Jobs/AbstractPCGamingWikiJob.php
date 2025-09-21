<?php

namespace Artryazanov\PCGamingWiki\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base abstract job for PCGamingWiki jobs providing shared queue traits,
 * throttling helper and a stable unique identifier.
 */
abstract class AbstractPCGamingWikiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute provided callback with global throttle.
     * Uses the same config key as Wikipedia package for consistency.
     */
    protected function executeWithThrottle(callable $callback): void
    {
        $startedAt = microtime(true);
        $callback();

        $delayMs = (int) config('pcgamingwiki.throttle_milliseconds', 1000);
        if ($delayMs > 0) {
            $elapsedMicros = (int) ((microtime(true) - $startedAt) * 1_000_000);
            $sleepMicros = max(0, ($delayMs * 1000) - $elapsedMicros);
            if ($sleepMicros > 0) {
                usleep($sleepMicros);
            }
        }
    }
}
