<?php

namespace Artryazanov\PCGamingWiki\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'clean_title',
        'release_date',
        'release_year',
        'cover_url',
        'wikipage_id',
    ];

    protected $appends = [
        'title',
        'pcgw_url',
    ];

    protected $casts = [
        'release_year' => 'integer',
    ];

    /**
     * Related PCGamingWiki page meta.
     */
    public function wikipage(): BelongsTo
    {
        return $this->belongsTo(Wikipage::class);
    }

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
     * Accessor: proxy title to related wikipage->title
     */
    public function getTitleAttribute(): ?string
    {
        return $this->wikipage?->title;
    }

    /**
     * Accessor: proxy pcgw_url to related wikipage->pcgw_url
     */
    public function getPcgwUrlAttribute(): ?string
    {
        return $this->wikipage?->pcgw_url;
    }
}
