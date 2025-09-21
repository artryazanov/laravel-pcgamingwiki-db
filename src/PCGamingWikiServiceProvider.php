<?php

namespace Artryazanov\PCGamingWiki;

use Artryazanov\PCGamingWiki\Console\SyncGamesCommand;
use Illuminate\Support\ServiceProvider;

class PCGamingWikiServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/pcgamingwiki.php', 'pcgamingwiki');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration file
            $this->publishes([
                __DIR__ . '/../config/pcgamingwiki.php' => config_path('pcgamingwiki.php'),
            ], 'config');

            // Register the Artisan command
            $this->commands([
                SyncGamesCommand::class,
            ]);

            // Optionally publish migrations so the host app can copy them
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }

        // Auto-load migrations from the package so publishing is not required
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
