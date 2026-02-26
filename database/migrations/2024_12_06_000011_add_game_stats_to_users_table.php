<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Game statistics
            $table->integer('total_games_played')->default(0)->after('is_active');
            $table->integer('total_wins')->default(0)->after('total_games_played');
            $table->integer('total_losses')->default(0)->after('total_wins');
            $table->integer('lifetime_score')->default(0)->after('total_losses');
            $table->integer('highest_score')->default(0)->after('lifetime_score');
            
            // Streaks
            $table->integer('current_streak')->default(0)->after('highest_score');
            $table->integer('best_streak')->default(0)->after('current_streak');
            $table->date('last_play_date')->nullable()->after('best_streak');
            
            // Favorites
            $table->foreignId('favorite_business_id')->nullable()->after('last_play_date');
            $table->foreignId('favorite_game_id')->nullable()->after('favorite_business_id');
            
            // Rewards
            $table->integer('total_rewards_won')->default(0)->after('favorite_game_id');
            $table->integer('total_rewards_redeemed')->default(0)->after('total_rewards_won');
            $table->decimal('total_savings', 10, 2)->default(0)->after('total_rewards_redeemed');
            
            // Badges
            $table->integer('total_badges')->default(0)->after('total_savings');
            $table->integer('badge_points')->default(0)->after('total_badges');
            
            // Level/XP system
            $table->integer('xp')->default(0)->after('badge_points');
            $table->integer('level')->default(1)->after('xp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'total_games_played',
                'total_wins',
                'total_losses',
                'lifetime_score',
                'highest_score',
                'current_streak',
                'best_streak',
                'last_play_date',
                'favorite_business_id',
                'favorite_game_id',
                'total_rewards_won',
                'total_rewards_redeemed',
                'total_savings',
                'total_badges',
                'badge_points',
                'xp',
                'level',
            ]);
        });
    }
};

