<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Badge;
use App\Models\GamePack;
use Illuminate\Database\Seeder;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        // Basic Tier Games
        $basicGames = [
            [
                'name' => 'Memory Match',
                'slug' => 'memory-match',
                'description' => 'Flip tiles to match pairs. Test your memory!',
                'type' => 'memory_match',
                'tier' => 'basic',
                'category' => 'casual',
                'icon' => '🧠',
                'time_limit' => 120,
                'min_score' => 0,
                'max_score' => null,
                'config' => ['gridSize' => 4],
            ],
            [
                'name' => 'Word Search',
                'slug' => 'word-search',
                'description' => 'Find all the hidden words in the grid.',
                'type' => 'word_search',
                'tier' => 'basic',
                'category' => 'puzzle',
                'icon' => '🔤',
                'time_limit' => 180,
                'min_score' => 0,
                'max_score' => null,
            ],
            [
                'name' => 'Snake',
                'slug' => 'snake',
                'description' => 'Classic snake game. Eat apples to grow!',
                'type' => 'snake',
                'tier' => 'basic',
                'category' => 'arcade',
                'icon' => '🐍',
                'time_limit' => null,
                'min_score' => 0,
                'max_score' => null,
            ],
            [
                'name' => 'Tap Counter',
                'slug' => 'tap-counter',
                'description' => 'Tap as fast as you can! Build combos for bonus points.',
                'type' => 'tap_counter',
                'tier' => 'basic',
                'category' => 'casual',
                'icon' => '👆',
                'time_limit' => 30,
                'min_score' => 0,
                'max_score' => null,
            ],
        ];

        // Pro Tier Games
        $proGames = [
            [
                'name' => 'Brick Breaker',
                'slug' => 'brick-breaker',
                'description' => 'Classic paddle and ball game. Break all the bricks!',
                'type' => 'brick_breaker',
                'tier' => 'pro',
                'category' => 'arcade',
                'icon' => '🧱',
                'time_limit' => null,
                'min_score' => 0,
                'max_score' => null,
            ],
            [
                'name' => 'QR Dash',
                'slug' => 'qr-dash',
                'description' => 'Jump through obstacles! Control the QR code square.',
                'type' => 'qr_dash',
                'tier' => 'pro',
                'category' => 'arcade',
                'icon' => '⬛',
                'time_limit' => null, // Level 3 is time-based but handled internally
                'min_score' => 0,
                'max_score' => null,
            ],
            [
                'name' => 'Cupcake Catcher',
                'slug' => 'cupcake-catcher',
                'description' => 'Catch the falling desserts! Avoid the bombs.',
                'type' => 'cupcake_catcher',
                'tier' => 'pro',
                'category' => 'family',
                'icon' => '🧁',
                'time_limit' => 60,
                'min_score' => 0,
                'max_score' => null,
            ],
            [
                'name' => 'Slice Saver',
                'slug' => 'slice-saver',
                'description' => 'Save the falling pizza slices on your plate!',
                'type' => 'slice_saver',
                'tier' => 'pro',
                'category' => 'food',
                'icon' => '🍕',
                'time_limit' => 60,
                'min_score' => 0,
                'max_score' => null,
            ],
        ];

        // Create/Update Basic Games
        foreach ($basicGames as $index => $game) {
            Game::updateOrCreate(
                ['slug' => $game['slug']],
                array_merge($game, [
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ])
            );
        }

        // Create/Update Growth (Pro tier) Games
        foreach ($proGames as $index => $game) {
            Game::updateOrCreate(
                ['slug' => $game['slug']],
                array_merge($game, [
                    'is_active' => true,
                    'sort_order' => $index + 10,
                ])
            );
        }

        // Note: We intentionally do NOT seed purchasable Game Packs here.
        // The current core lineup is unlocked by subscription tier (Starter/Growth/Pro).
        // Future games can be sold as add-ons/packs via the Admin QRcade interface.
        //
        // If legacy packs exist from older installs, disable them so tiers remain the unlock mechanism.
        GamePack::whereIn('slug', ['starter-pack', 'pro-pack', 'family-dining-pack'])
            ->update(['is_active' => false]);

        // Create Default Badges
        $badges = [
            // Existing Game Badges
            [
                'name' => 'Play 3 Games',
                'slug' => 'first-play',
                'description' => 'Play 3 games',
                'icon' => '🎮',
                'category' => 'milestone',
                'rarity' => 'common',
                // tuned: avoid unlocking multiple badges on the very first game
                'requirements' => ['type' => 'games_played', 'value' => 3],
                'points' => 10,
            ],
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Play 10 games',
                'icon' => '🎯',
                'category' => 'milestone',
                'rarity' => 'uncommon',
                'requirements' => ['type' => 'games_played', 'value' => 10],
                'points' => 50,
            ],
            [
                'name' => 'Game Master',
                'slug' => 'game-master',
                'description' => 'Play 100 games',
                'icon' => '👑',
                'category' => 'milestone',
                'rarity' => 'epic',
                'requirements' => ['type' => 'games_played', 'value' => 100],
                'points' => 500,
            ],
            [
                'name' => 'Win 3 Games',
                'slug' => 'first-win',
                'description' => 'Win 3 games',
                'icon' => '🏆',
                'category' => 'achievement',
                'rarity' => 'common',
                // tuned: prevent "first game = instant win badge" spam
                'requirements' => ['type' => 'wins', 'value' => 3],
                'points' => 25,
            ],
            [
                'name' => 'Winner Winner',
                'slug' => 'winner-winner',
                'description' => 'Win 10 games',
                'icon' => '🥇',
                'category' => 'achievement',
                'rarity' => 'rare',
                'requirements' => ['type' => 'wins', 'value' => 10],
                'points' => 100,
            ],
            [
                'name' => 'Week Warrior',
                'slug' => 'week-warrior',
                'description' => 'Play 7 days in a row',
                'icon' => '🔥',
                'category' => 'streak',
                'rarity' => 'rare',
                'requirements' => ['type' => 'streak', 'value' => 7],
                'points' => 150,
            ],
            [
                'name' => 'High Scorer',
                'slug' => 'high-scorer',
                'description' => 'Score over 5000 in a single game',
                'icon' => '⭐',
                'category' => 'achievement',
                'rarity' => 'uncommon',
                // tuned: some games can hit 1,000 too easily on first try
                'requirements' => ['type' => 'score', 'value' => 5000],
                'points' => 75,
            ],
            [
                'name' => 'Speed Demon',
                'slug' => 'speed-demon',
                'description' => 'Complete a game in under 20 seconds',
                'icon' => '⚡',
                'category' => 'achievement',
                'rarity' => 'rare',
                // tuned: 30 seconds was too easy for short/fast games
                'requirements' => ['type' => 'speed_run', 'value' => 20],
                'points' => 200,
            ],

            // New Scan-Based Badges
            [
                'name' => 'First Scan',
                'slug' => 'first-scan',
                'description' => 'Scan your first QR code',
                'icon' => '🎯',
                'category' => 'milestone',
                'rarity' => 'common',
                'requirements' => ['type' => 'total_scans', 'value' => 1],
                'points' => 10,
            ],
            [
                'name' => 'Scanner Novice',
                'slug' => 'scanner-novice',
                'description' => 'Scan 5 different QR codes',
                'icon' => '📱',
                'category' => 'milestone',
                'rarity' => 'uncommon',
                'requirements' => ['type' => 'total_scans', 'value' => 5],
                'points' => 25,
            ],
            [
                'name' => 'QR Hunter',
                'slug' => 'qr-hunter',
                'description' => 'Scan 25 different QR codes',
                'icon' => '🏹',
                'category' => 'milestone',
                'rarity' => 'rare',
                'requirements' => ['type' => 'total_scans', 'value' => 25],
                'points' => 100,
            ],
            [
                'name' => 'Scan Master',
                'slug' => 'scan-master',
                'description' => 'Scan 100 different QR codes',
                'icon' => '🔍',
                'category' => 'milestone',
                'rarity' => 'epic',
                'requirements' => ['type' => 'total_scans', 'value' => 100],
                'points' => 500,
            ],
            [
                'name' => 'Ultimate Scanner',
                'slug' => 'ultimate-scanner',
                'description' => 'Scan 500 different QR codes',
                'icon' => '🛰️',
                'category' => 'milestone',
                'rarity' => 'legendary',
                'requirements' => ['type' => 'total_scans', 'value' => 500],
                'points' => 1000,
            ],

            // New Redemption-Based Badges
            [
                'name' => 'First Redemption',
                'slug' => 'first-redemption',
                'description' => 'Redeem your first reward',
                'icon' => '🎁',
                'category' => 'achievement',
                'rarity' => 'common',
                'requirements' => ['type' => 'total_redeemed', 'value' => 1],
                'points' => 15,
            ],
            [
                'name' => 'Deal Seeker',
                'slug' => 'deal-seeker',
                'description' => 'Redeem 10 rewards',
                'icon' => '💰',
                'category' => 'achievement',
                'rarity' => 'uncommon',
                'requirements' => ['type' => 'total_redeemed', 'value' => 10],
                'points' => 75,
            ],
            [
                'name' => 'Redemption Champion',
                'slug' => 'redemption-champion',
                'description' => 'Redeem 50 rewards',
                'icon' => '🏅',
                'category' => 'achievement',
                'rarity' => 'rare',
                'requirements' => ['type' => 'total_redeemed', 'value' => 50],
                'points' => 200,
            ],
            [
                'name' => 'Savings Expert',
                'slug' => 'savings-expert',
                'description' => 'Redeem 100 rewards',
                'icon' => '💸',
                'category' => 'achievement',
                'rarity' => 'epic',
                'requirements' => ['type' => 'total_redeemed', 'value' => 100],
                'points' => 750,
            ],

            // New Savings-Based Badges
            [
                'name' => 'Money Saver',
                'slug' => 'money-saver',
                'description' => 'Save $50 or more',
                'icon' => '💵',
                'category' => 'achievement',
                'rarity' => 'uncommon',
                'requirements' => ['type' => 'total_savings', 'value' => 50.00],
                'points' => 50,
            ],
            [
                'name' => 'Budget Master',
                'slug' => 'budget-master',
                'description' => 'Save $250 or more',
                'icon' => '🤑',
                'category' => 'achievement',
                'rarity' => 'rare',
                'requirements' => ['type' => 'total_savings', 'value' => 250.00],
                'points' => 150,
            ],
            [
                'name' => 'Wealth Builder',
                'slug' => 'wealth-builder',
                'description' => 'Save $1000 or more',
                'icon' => '🏦',
                'category' => 'achievement',
                'rarity' => 'epic',
                'requirements' => ['type' => 'total_savings', 'value' => 1000.00],
                'points' => 500,
            ],
            [
                'name' => 'Fortune Finder',
                'slug' => 'fortune-finder',
                'description' => 'Save $5000 or more',
                'icon' => '💎',
                'category' => 'achievement',
                'rarity' => 'legendary',
                'requirements' => ['type' => 'total_savings', 'value' => 5000.00],
                'points' => 2000,
            ],

            // New Business Diversity Badges
            [
                'name' => 'Business Explorer',
                'slug' => 'business-explorer',
                'description' => 'Scan at 3 different businesses',
                'icon' => '🌍',
                'category' => 'explorer',
                'rarity' => 'uncommon',
                'requirements' => ['type' => 'business_diversity_scans', 'value' => 3],
                'points' => 75,
            ],
            [
                'name' => 'Local Legend',
                'slug' => 'local-legend',
                'description' => 'Scan at 10 different businesses',
                'icon' => '🏛️',
                'category' => 'explorer',
                'rarity' => 'rare',
                'requirements' => ['type' => 'business_diversity_scans', 'value' => 10],
                'points' => 150,
            ],
            [
                'name' => 'Empire Builder',
                'slug' => 'empire-builder',
                'description' => 'Scan at 25 different businesses',
                'icon' => '🌐',
                'category' => 'explorer',
                'rarity' => 'epic',
                'requirements' => ['type' => 'business_diversity_scans', 'value' => 25],
                'points' => 400,
            ],
            [
                'name' => 'Redemption Rover',
                'slug' => 'redemption-rover',
                'description' => 'Redeem at 15 different businesses',
                'icon' => '🚀',
                'category' => 'explorer',
                'rarity' => 'legendary',
                'requirements' => ['type' => 'business_diversity_redemptions', 'value' => 15],
                'points' => 800,
            ],

            // New Combo Achievement Badges
            [
                'name' => 'Power User',
                'slug' => 'power-user',
                'description' => 'Scan 50+ QR codes and redeem 20+ rewards',
                'icon' => '⚡',
                'category' => 'champion',
                'rarity' => 'rare',
                'requirements' => ['type' => 'combo_power_user', 'scans' => 50, 'redemptions' => 20],
                'points' => 300,
            ],
            [
                'name' => 'Elite Member',
                'slug' => 'elite-member',
                'description' => 'Save $500+ and scan 100+ QR codes',
                'icon' => '👑',
                'category' => 'champion',
                'rarity' => 'epic',
                'requirements' => ['type' => 'combo_elite_member', 'savings' => 500.00, 'scans' => 100],
                'points' => 1000,
            ],
        ];

        foreach ($badges as $badge) {
            // Only create if badge doesn't already exist
            Badge::firstOrCreate(
                ['slug' => $badge['slug']],
                array_merge($badge, ['is_active' => true])
            );
        }

        $this->command->info('Games and Badges seeded successfully!');
    }
}

