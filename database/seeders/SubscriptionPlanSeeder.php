<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small businesses getting started with QR promotions',
                'monthly_price' => 19.99,
                'yearly_price' => 199.99, // ~17% savings
                'features' => [
                    // Limits
                    'qr_codes' => 10,
                    'promotions' => 5,
                    'scans_per_month' => 1000,
                    'employees' => 2,
                    'analytics_days' => 14,

                    // CRM (included in all tiers)
                    'crm' => true,
                    'crm_automations' => true,
                    
                    // Features
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
                    'merch_referral_qr' => false,
                    'remove_branding' => false,
                    
                    // Merch Limits - Starter: t-shirts only
                    'merch_categories' => ['t-shirt'],
                    'merch_orders_per_month' => 5,
                    
                    // QRcade Games
                    'qrcade' => true,
                    'basic_games' => true,
                    'pro_games' => false,
                    'premium_games' => false,
                    'seasonal_games' => false,
                    'leaderboards' => false,
                    'tournaments' => false,
                    'game_analytics' => false,
                    'games' => 4,
                    'game_plays_per_month' => 1000,

                    // Customer directory / featured promo
                    'featured_promo' => false,
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'For growing businesses that need more power and features',
                'monthly_price' => 39.99,
                'yearly_price' => 399.99,
                'features' => [
                    // Limits
                    'qr_codes' => 50,
                    'promotions' => 25,
                    'scans_per_month' => 10000,
                    'employees' => 10,
                    'analytics_days' => 30,

                    // CRM (included in all tiers)
                    'crm' => true,
                    'crm_automations' => true,
                    
                    // Features
                    'white_label' => false,
                    'api_access' => false,
                    'priority_support' => false,
                    'advanced_analytics' => true,
                    'ai_insights' => true,
                    'cross_promotions' => true,
                    'stackable_pools' => true,
                    'print_studio' => true,
                    'print_kits' => true,
                    'merch_store' => true,
                    'merch_referral_qr' => true,
                    'remove_branding' => false,
                    
                    // Merch Limits - Growth tier (t-shirts + hoodies)
                    'merch_categories' => ['t-shirt', 'hoodie'],
                    'merch_orders_per_month' => 25,
                    
                    // QRcade Games
                    'qrcade' => true,
                    'basic_games' => true,
                    'pro_games' => true,
                    'premium_games' => false,
                    'seasonal_games' => false,
                    'leaderboards' => true,
                    'tournaments' => false,
                    'game_analytics' => true,
                    'games' => 8,
                    'game_plays_per_month' => 10000,
                    'leaderboard_limit' => 5,

                    // Customer directory / featured promo
                    'featured_promo' => true,
                ],
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Full-featured plan for established businesses',
                'monthly_price' => 79.99,
                'yearly_price' => 799.99,
                'features' => [
                    // Limits
                    'qr_codes' => 200,
                    'promotions' => 100,
                    'scans_per_month' => 50000,
                    'employees' => 50,
                    'analytics_days' => 90,

                    // CRM (included in all tiers)
                    'crm' => true,
                    'crm_automations' => true,
                    
                    // Features
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
                    'merch_referral_qr' => true,
                    'remove_branding' => true,
                    
                    // Merch Limits - Pro tier (all products)
                    'merch_categories' => ['all'],
                    'merch_orders_per_month' => 100,
                    
                    // QRcade Games
                    'qrcade' => true,
                    'basic_games' => true,
                    'pro_games' => true,
                    'premium_games' => true,
                    'seasonal_games' => true,
                    'leaderboards' => true,
                    'tournaments' => true,
                    'game_analytics' => true,
                    'custom_game_schedule' => true,
                    'games' => 10, // All current core games (4 basic + 4 pro + 2 premium)
                    'game_plays_per_month' => 50000,
                    'leaderboard_limit' => 20,
                    'tournament_limit' => 10,

                    // Customer directory / featured promo
                    'featured_promo' => true,
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'White-label solution with unlimited scale for agencies & large orgs',
                'monthly_price' => 199.99,
                'yearly_price' => 1999.99,
                'features' => [
                    // Limits (unlimited)
                    'qr_codes' => -1,
                    'promotions' => -1,
                    'scans_per_month' => -1,
                    'employees' => -1,
                    'analytics_days' => 365,

                    // CRM (included in all tiers)
                    'crm' => true,
                    'crm_automations' => true,
                    
                    // All Features
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
                    'merch_referral_qr' => true,
                    'remove_branding' => true,
                    'sla_guarantee' => true,
                    
                    // Merch Limits - Enterprise (unlimited)
                    'merch_categories' => ['all'],
                    'merch_orders_per_month' => -1, // Unlimited
                    'merch_wholesale_pricing' => true,
                    'merch_custom_products' => true,
                    
                    // All QRcade Features
                    'qrcade' => true,
                    'basic_games' => true,
                    'pro_games' => true,
                    'premium_games' => true,
                    'seasonal_games' => true,
                    'leaderboards' => true,
                    'tournaments' => true,
                    'game_analytics' => true,
                    'custom_game_schedule' => true,
                    'custom_branded_games' => true,
                    'white_label_games' => true,
                    'games' => -1,
                    'game_plays_per_month' => -1,
                    'leaderboard_limit' => -1,
                    'tournament_limit' => -1,

                    // Customer directory / featured promo
                    'featured_promo' => true,
                ],
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('Created/updated ' . count($plans) . ' subscription plans.');
    }
}
