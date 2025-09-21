<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pcgw_game_links')) {
            Schema::create('pcgw_game_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('game_id')->constrained('pcgw_games')->onDelete('cascade');
                $table->string('site', 100)->index()->comment('Key of external resource (e.g., igdb, hltb, official-site)');
                $table->string('title')->nullable();
                // Use longer length for URLs to be safe across drivers
                $table->string('url', 1024)->index();
                $table->timestamps();

                $table->unique(['game_id', 'site']);
                $table->unique(['game_id', 'url']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pcgw_game_links');
    }
};
