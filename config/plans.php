<?php

/**
 * Legacy plan defaults (dev/test fallback only).
 *
 * Production should use the database `subscription_plans` table as the single
 * source of truth for pricing, features, and limits.
 */
return [
    'starter' => [
        'features' => [
            // Limits
            'qr_codes' => 10,
            'promotions' => 5,
            'scans_per_month' => 1000,
            'employees' => 2,
            'analytics_days' => 14,
            'games' => 4,
            'game_plays_per_month' => 1000,

            // Feature flags
            'white_label' => false,
            'api_access' => false,
            'priority_support' => false,
            'advanced_analytics' => false,
            'ai_insights' => false,
            'cross_promotions' => false,
            'stackable_pools' => false,
            'print_studio' => true,
            'print_kits' => false,
            'merch_store' => true,
            'remove_branding' => false,

            // QRcade feature flags
            'qrcade' => true,
            'pro_games' => false,      // Growth+
            'premium_games' => false,  // Pro+
            'seasonal_games' => false,
            'leaderboards' => false,
            'tournaments' => false,
            'game_analytics' => false,
        ],
    ],
    'growth' => [
        'features' => [
            // Limits
            'qr_codes' => 50,
            'promotions' => 25,
            'scans_per_month' => 10000,
            'employees' => 10,
            'analytics_days' => 30,
            'games' => 8,
            'game_plays_per_month' => 10000,
            'leaderboard_limit' => 5,

            // Feature flags
            'white_label' => false,
            'api_access' => false,
            'priority_support' => false,
            'advanced_analytics' => true,
            'ai_insights' => false,
            'cross_promotions' => true,
            'stackable_pools' => true,
            'print_studio' => true,
            'print_kits' => true,
            'merch_store' => true,
            'remove_branding' => false,

            // QRcade feature flags
            'qrcade' => true,
            'pro_games' => true,
            'premium_games' => false,
            'seasonal_games' => false,
            'leaderboards' => true,
            'tournaments' => false,
            'game_analytics' => true,
        ],
    ],
    'pro' => [
        'features' => [
            // Limits
            'qr_codes' => 200,
            'promotions' => 100,
            'scans_per_month' => 50000,
            'employees' => 50,
            'analytics_days' => 90,
            'games' => 10,
            'game_plays_per_month' => 50000,
            'leaderboard_limit' => 20,
            'tournament_limit' => 10,

            // Feature flags
            'white_label' => false,
            'api_access' => true,
            'priority_support' => true,
            'advanced_analytics' => true,
            'ai_insights' => true,
            'cross_promotions' => true,
            'stackable_pools' => true,
            'print_studio' => true,
            'print_kits' => true,
            'merch_store' => true,
            'remove_branding' => true,

            // QRcade feature flags
            'qrcade' => true,
            'pro_games' => true,
            'premium_games' => true,
            'seasonal_games' => true,
            'leaderboards' => true,
            'tournaments' => true,
            'game_analytics' => true,
            'custom_game_schedule' => true,
        ],
    ],
    'enterprise' => [
        'features' => [
            'qr_codes' => -1,
            'promotions' => -1,
            'scans_per_month' => -1,
            'employees' => -1,
            'analytics_days' => 365,
            'games' => -1,
            'game_plays_per_month' => -1,
            'leaderboard_limit' => -1,
            'tournament_limit' => -1,

            'white_label' => true,
            'api_access' => true,
            'priority_support' => true,
            'advanced_analytics' => true,
            'ai_insights' => true,
            'cross_promotions' => true,
            'stackable_pools' => true,
            'print_studio' => true,
            'print_kits' => true,
            'merch_store' => true,
            'remove_branding' => true,

            'qrcade' => true,
            'pro_games' => true,
            'premium_games' => true,
            'seasonal_games' => true,
            'leaderboards' => true,
            'tournaments' => true,
            'game_analytics' => true,
            'custom_game_schedule' => true,
            'custom_branded_games' => true,
        ],
    ],
];
