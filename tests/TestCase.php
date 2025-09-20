<?php

namespace Tests;

use Artryazanov\PCGamingWiki\PCGamingWikiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            PCGamingWikiServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Run package migrations against in-memory SQLite
        $this->artisan('migrate', ['--force' => true])->run();
    }

    protected function getEnvironmentSetUp($app)
    {
        // Ensure queue runs synchronously during tests
        $app['config']->set('queue.default', 'sync');
        // Use in-memory cache to support job assertions
        $app['config']->set('cache.default', 'array');

        // Provide sensible defaults for package config in tests
        $app['config']->set('pcgamingwiki.api_url', 'https://www.pcgamingwiki.com/w/api.php');
        $app['config']->set('pcgamingwiki.format', 'json');
        $app['config']->set('pcgamingwiki.limit', 5);

        // Use in-memory SQLite for tests to avoid external DB dependency
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
    }
}
