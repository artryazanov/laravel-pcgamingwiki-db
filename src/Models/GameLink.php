<?php

namespace Artryazanov\PCGamingWiki\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artryazanov\\PCGamingWiki\\Models\\GameLink
 *
 * @property int $id
 * @property int $game_id
 * @property string $site
 * @property string|null $title
 * @property string $url
 */
class GameLink extends Model
{
    protected $table = 'pcgw_game_links';

    protected $fillable = [
        'game_id',
        'site',
        'title',
        'url',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
