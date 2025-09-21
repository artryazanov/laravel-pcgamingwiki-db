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
                // Use longer length for URLs to be safe across drivers; avoid indexing long utf8mb4 strings to prevent MySQL key length issues
                $table->string('url', 1024);
                $table->timestamps();

                // Ensure only one link per site per game; URL itself is not uniquely indexed to stay within MySQL index length limits
                $table->unique(['game_id', 'site']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pcgw_game_links');
    }
};
