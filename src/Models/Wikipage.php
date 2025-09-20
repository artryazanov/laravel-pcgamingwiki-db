<?php

namespace Artryazanov\PCGamingWiki\Models;

use Illuminate\Database\Eloquent\Model;

class Wikipage extends Model
{
    protected $table = 'pcgw_game_wikipages';

    protected $fillable = [
        'title',
        'pcgw_url',
        'description',
        'wikitext',
    ];
}
