<?php

namespace Artryazanov\PCGamingWiki\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Artryazanov\\PCGamingWiki\\Models\\Game
 *
 * @property int $id
 * @property string|null $title Game title from PCGamingWiki
 * @property string|null $pcgw_url Canonical URL to the PCGamingWiki page
 * @property string|null $clean_title Normalized game title without disambiguation
 * @property string|null $cover_url URL of the cover image on PCGamingWiki
 * @property string|null $release_date First known release date (raw value)
 * @property int|null $release_year First 4-digit release year parsed
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, Genre> $genres
 * @property-read Collection<int, Platform> $platforms
 * @property-read Collection<int, Mode> $modes
 * @property-read Collection<int, Series> $series
 * @property-read Collection<int, Engine> $engines
 * @property-read Collection<int, Company> $companies
 * @property-read Collection<int, Company> $developersCompanies
 * @property-read Collection<int, Company> $publishersCompanies
 *
 * @method static Builder|Game newModelQuery()
 * @method static Builder|Game newQuery()
 * @method static Builder|Game query()
 */
class Game extends Model
{
    /**
     * The database table used by the model.
     */
    protected $table = 'pcgw_games';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'pcgw_url',
        'clean_title',
        'release_date',
        'release_year',
        'cover_url',
    ];

    protected $casts = [
        'release_year' => 'integer',
    ];

    /**
     * Genres relation (many-to-many via pivot).
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'pcgw_game_game_genre');
    }

    /**
     * Platforms relation (many-to-many via pivot).
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'pcgw_game_game_platform');
    }

    /**
     * Modes relation (many-to-many via pivot).
     */
    public function modes(): BelongsToMany
    {
        return $this->belongsToMany(Mode::class, 'pcgw_game_game_mode');
    }

    /**
     * Series relation (many-to-many via pivot).
     */
    public function series(): BelongsToMany
    {
        return $this->belongsToMany(Series::class, 'pcgw_game_game_series');
    }

    /**
     * Engines relation (many-to-many via pivot).
     */
    public function engines(): BelongsToMany
    {
        return $this->belongsToMany(Engine::class, 'pcgw_game_game_engine');
    }

    /**
     * Companies relation (many-to-many via pivot with 'role').
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'pcgw_game_game_company')->withPivot('role');
    }

    /**
     * Companies acting as developers.
     */
    public function developersCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'pcgw_game_game_company')->wherePivot('role', 'developer');
    }

    /**
     * Companies acting as publishers.
     */
    public function publishersCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'pcgw_game_game_company')->wherePivot('role', 'publisher');
    }

    /**
     * External links related to this game.
     */
    public function links(): HasMany
    {
        return $this->hasMany(GameLink::class, 'game_id');
    }
}
