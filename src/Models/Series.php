<?php

namespace Artryazanov\PCGamingWiki\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Series extends Model
{
    protected $table = 'pcgw_game_series';

    protected $fillable = [
        'name',
        'wikipage_id',
    ];

    public function wikipage(): BelongsTo
    {
        return $this->belongsTo(Wikipage::class);
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'pcgw_game_game_series');
    }
}
