<?php

namespace App\Services;

use App\Models\Business;

class OnboardingService
{
    /**
     * Tier hierarchy for comparison
     */
    private const TIER_ORDER = ['starter' => 1, 'growth' => 2, 'pro' => 3, 'enterprise' => 4];

    /**
     * Get all onboarding steps
     */
    public function getAllSteps(): array
    {
        return [
            [
                'id' => 'create_promotion',
                'title' => 'Create Your First Promotion',
                'description' => 'Set up a discount or deal for customers',
                'route' => '/business/promotions/create',
                'required_tier' => null, // All tiers
                'upsell_tier' => null,
                'check' => fn($business) => $business->promotions()->count() > 0,
            ],
            [
                'id' => 'create_qr_code',
                'title' => 'Create QR Code',
                'description' => 'Generate a QR code and attach your promotion',
                'route' => '/business/qr-codes/create',
                'required_tier' => null,
                'upsell_tier' => null,
                'check' => fn($business) => $business->qrCodes()->count() > 0,
            ],
            [
                'id' => 'attach_promotion_to_qr',
                'title' => 'Attach Promotion to QR Code',
                'description' => 'Link your promotion to your QR code',
                'route' => '/business/qr-codes',
                'required_tier' => null,
                'upsell_tier' => null,
                'check' => fn($business) => $business->qrCodes()->whereNotNull('promotion_id')->count() > 0,
            ],
            [
                'id' => 'setup_basic_games',
                'title' => 'Set Up Basic Games',
                'description' => 'Add games to your QR codes for customer engagement',
                'route' => '/business/qrcade',
                'required_tier' => null, // All tiers have basic games
                'upsell_tier' => null,
                'check' => fn($business) => $business->businessGames()->count() > 0,
            ],
            [
                'id' => 'setup_pro_games',
                'title' => 'Unlock Pro Games',
                'description' => 'Access advanced games with higher engagement',
                'route' => '/business/qrcade/games',
                'required_tier' => 'growth',
                'upsell_tier' => 'growth',
                'check' => fn($business) => $business->canAccess('pro_games') && $business->businessGames()->where('is_enabled', true)->whereHas('game', fn($q) => $q->where('tier', 'pro'))->count() > 0,
            ],
            [
                'id' => 'setup_leaderboards',
                'title' => 'Create Leaderboards',
                'description' => 'Add competitive leaderboards to drive engagement',
                'route' => '/business/qrcade/leaderboards',
                'required_tier' => 'growth',
                'upsell_tier' => 'growth',
                'check' => fn($business) => $business->canAccess('leaderboards') && $business->leaderboards()->count() > 0,
            ],
            [
                'id' => 'download_qr_code',
                'title' => 'Download Your QR Code',
                'description' => 'Download and print your QR code to start using it',
                'route' => '/business/qr-codes',
                'required_tier' => null,
                'upsell_tier' => null,
                'check' => fn($business) => in_array('download_qr_code', $business->onboarding_steps ?? []),
            ],
        ];
    }

    /**
     * Get only the steps that the business can complete on their current tier
     */
    public function getStepsForBusiness(Business $business): array
    {
        $allSteps = $this->getAllSteps();
        $currentTierLevel = $this->getTierLevel($business->subscription_tier ?? 'starter');

        $steps = [];

        foreach ($allSteps as $step) {
            // Skip steps that require a higher tier
            if ($step['required_tier'] !== null) {
                $requiredTierLevel = self::TIER_ORDER[$step['required_tier']] ?? 1;
                if ($currentTierLevel < $requiredTierLevel) {
                    continue; // Don't include locked steps in the main checklist
                }
            }

            $step['is_completed'] = $business->checkStepCompletion($step);
            $step['is_available'] = true;
            $step['is_locked'] = false;
            $step['upsell_message'] = null;

            // Remove closure before serialization (Inertia can't serialize closures)
            unset($step['check']);

            $steps[] = $step;
        }

        return $steps;
    }

    /**
     * Get upsell steps (features available on higher tiers) for display
     */
    public function getUpsellStepsForBusiness(Business $business): array
    {
        $allSteps = $this->getAllSteps();
        $currentTierLevel = $this->getTierLevel($business->subscription_tier ?? 'starter');

        $upsellSteps = [];

        foreach ($allSteps as $step) {
            if ($step['required_tier'] === null) {
                continue; // Skip steps available to all tiers
            }

            $requiredTierLevel = self::TIER_ORDER[$step['required_tier']] ?? 1;
            if ($currentTierLevel >= $requiredTierLevel) {
                continue; // Skip steps the business already has access to
            }

            $upsellSteps[] = [
                'id' => $step['id'],
                'title' => $step['title'],
                'description' => $step['description'],
                'upsell_tier' => $step['upsell_tier'],
                'upsell_message' => 'Upgrade to ' . $this->getTierDisplayName($step['upsell_tier']) . ' to unlock',
            ];
        }

        return $upsellSteps;
    }

    /**
     * Get tier level number for comparison
     */
    private function getTierLevel(string $tier): int
    {
        return self::TIER_ORDER[$tier] ?? 1;
    }

    /**
     * Get tier name for display
     */
    public function getTierDisplayName(string $tier): string
    {
        return match($tier) {
            'starter' => 'Starter',
            'growth' => 'Growth',
            'pro' => 'Pro',
            'enterprise' => 'Enterprise',
            default => ucfirst($tier),
        };
    }
}

