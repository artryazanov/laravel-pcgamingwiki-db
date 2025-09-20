# Laravel PCGamingWiki DB

A Laravel package to sync game data from PCGamingWiki using its MediaWiki API (Cargo). It provides:

- A ready-to-run database schema for games and related taxonomy (companies, platforms, genres, modes, series, engines) with pivot tables
- An Artisan command that queues background jobs to fetch batches of games and persist normalized data
- Configurable API endpoint and batch size

This package is framework-agnostic within the Laravel ecosystem and uses Laravel's HTTP client and queue system.

## Requirements

- PHP >= 8.0
- Laravel components ^10.0 (support, console, database, queue)

## Installation

Install via Composer in your Laravel application:

```bash
composer require artryazanov/laravel-pcgamingwiki-db
```

The service provider is auto-discovered by Laravel.

## Configuration

A default configuration is included. You can publish it to customize values:

```bash
php artisan vendor:publish --provider="Artryazanov\\PCGamingWiki\\PCGamingWikiServiceProvider" --tag=config
```

Available options (config/pcgamingwiki.php):

- api_url: PCGamingWiki MediaWiki API endpoint (default: https://www.pcgamingwiki.com/w/api.php)
- format: Response format (default: json)
- limit: Default batch size for API queries when not provided via CLI option (default: 50)

You may also set via environment variables:

```
PCGW_API_URL=https://www.pcgamingwiki.com/w/api.php
```

## Database migrations

Migrations are auto-loaded from the package, so you can apply them directly:

```bash
php artisan migrate
```

If you prefer to customize migrations in your app, publish them and then run migrate:

```bash
php artisan vendor:publish --provider="Artryazanov\\PCGamingWiki\\PCGamingWikiServiceProvider" --tag=migrations
php artisan migrate
```

The schema includes:

- pcgw_game_wikipages: Central page metadata (title, pcgw_url, description, wikitext)
- pcgw_games: Game records referencing a wikipage, with normalized fields like clean_title, release_date, release_year, cover_url
- Taxonomy tables: pcgw_game_companies, pcgw_game_platforms, pcgw_game_genres, pcgw_game_modes, pcgw_game_series, pcgw_game_engines
- Pivot tables: pcgw_game_game_company (with role: developer|publisher), pcgw_game_game_platform, pcgw_game_game_genre, pcgw_game_game_mode, pcgw_game_game_series, pcgw_game_game_engine

## Usage

Run the sync command to start fetching games from PCGamingWiki. The command dispatches a batch job that chains subsequent batches until no more records are returned.

```bash
php artisan pcgamingwiki:sync-games [--limit=50] [--offset=0]
```

Options:

- --limit: Number of rows to request per API call (defaults to config('pcgamingwiki.limit'))
- --offset: Starting offset into the result set

Important: The sync uses queued jobs. Ensure that your queue is configured and a worker is running before starting the sync:

```bash
php artisan queue:work
```

### What data is fetched?

The fetch job queries the Cargo table Infobox_game with fields including:

- Page name and page ID
- Developers
- Publisher
- Released (release date)
- Cover_URL (cover image reference or URL)

The save job then:

- Creates/updates a central Wikipage row using the canonical PCGamingWiki URL
- Upserts a Game row linked to the Wikipage
- Normalizes and saves developers and publishers as Company rows with pivot roles developer/publisher
- Parses and stores simple helpers like clean_title and release_year
- Normalizes Cover_URL into a usable URL (direct link or Special:FilePath)

## Models

You can use the Eloquent models provided by the package:

- Artryazanov\PCGamingWiki\Models\Game (table: pcgw_games)
- Artryazanov\PCGamingWiki\Models\Wikipage (table: pcgw_game_wikipages)
- Artryazanov\PCGamingWiki\Models\Company (table: pcgw_game_companies)
- Artryazanov\PCGamingWiki\Models\Platform (table: pcgw_game_platforms)
- Artryazanov\PCGamingWiki\Models\Genre (table: pcgw_game_genres)
- Artryazanov\PCGamingWiki\Models\Mode (table: pcgw_game_modes)
- Artryazanov\PCGamingWiki\Models\Series (table: pcgw_game_series)
- Artryazanov\PCGamingWiki\Models\Engine (table: pcgw_game_engines)

Example:

```php
use Artryazanov\PCGamingWiki\Models\Game;

$latest = Game::with(['wikipage', 'companies', 'platforms', 'genres'])
    ->orderByDesc('id')
    ->limit(10)
    ->get();

foreach ($latest as $game) {
    echo $game->title." (".$game->release_year.")\n";
    echo $game->pcgw_url."\n\n";
}
```

## Testing

Run the test suite with:

```bash
composer test
```

## License

This project is licensed under the Unlicense. See the LICENSE file for details.

## Credits and disclaimer

- Not affiliated with or endorsed by PCGamingWiki. All trademarks are property of their respective owners.
- Data is sourced from the public PCGamingWiki MediaWiki API.
