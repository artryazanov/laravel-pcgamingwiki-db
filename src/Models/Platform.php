<?php

namespace Artryazanov\PCGamingWiki\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Artryazanov\\PCGamingWiki\\Models\\Platform
 *
 * @property int $id
 * @property string $name
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Collection<int, Game> $games
 *
 * @method static Builder|Platform newModelQuery()
 * @method static Builder|Platform newQuery()
 * @method static Builder|Platform query()
 */
class Platform extends Model
{
    protected $table = 'pcgw_game_platforms';

    protected $fillable = [
        'name',
    ];

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'pcgw_game_game_platform');
    }
}
