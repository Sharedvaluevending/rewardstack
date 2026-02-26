<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'type',
        'description',
        'business_hours',
        'logo_path',
        'website',
        'facebook_url',
        'instagram_url',
        'phone',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'primary_color',
        'secondary_color',
        'custom_domain',
        'subscription_tier',
        'subscription_status',
        'subscription_cancel_at_period_end',
        'trial_ends_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'settings',
        'is_active',
        'is_testing_account',
        'onboarding_completed_at',
        'onboarding_steps',
        'onboarding_dismissed',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'settings' => 'array',
        'business_hours' => 'array',
        'is_active' => 'boolean',
        'is_testing_account' => 'boolean',
        'subscription_cancel_at_period_end' => 'boolean',
        'onboarding_dismissed' => 'boolean',
        'onboarding_steps' => 'array',
        'trial_ends_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    protected $appends = [
        'logo_url',
    ];

    // Relationships
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'business_id', 'id');
    }

    public function qrCodes()
    {
        return $this->hasMany(QRCode::class, 'business_id', 'id');
    }

    public function promotions()
    {
        return $this->hasMany(Promotion::class, 'business_id', 'id');
    }

    public function scans()
    {
        return $this->hasMany(Scan::class, 'business_id', 'id');
    }

    public function redemptions()
    {
        return $this->hasMany(Redemption::class, 'business_id', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'business_id', 'id');
    }

    public function followUps()
    {
        return $this->hasMany(BusinessFollowUp::class);
    }

    public function healthScores()
    {
        return $this->hasMany(BusinessHealthScore::class);
    }

    public function latestHealthScore()
    {
        return $this->hasOne(BusinessHealthScore::class)->latestOfMany('date');
    }

    public function aiInsights()
    {
        return $this->hasMany(AIInsight::class, 'business_id', 'id');
    }

    public function weeklyAIInsights()
    {
        return $this->hasMany(WeeklyAIInsight::class, 'business_id', 'id');
    }

    public function analyticsDaily()
    {
        return $this->hasMany(GameAnalyticsDaily::class, 'business_id', 'id');
    }

    // Partnerships
    public function sentPartnershipRequests()
    {
        return $this->hasMany(BusinessPartnership::class, 'requester_business_id');
    }

    public function receivedPartnershipRequests()
    {
        return $this->hasMany(BusinessPartnership::class, 'partner_business_id');
    }

    public function acceptedPartners()
    {
        return BusinessPartnership::forBusiness($this->id)->accepted()->get()->map(function ($partnership) {
            return $partnership->getOtherBusiness($this->id);
        });
    }

    // Cross-promotions
    public function crossPromotions()
    {
        return CrossPromotion::forBusiness($this->id);
    }

    // QRcade Games
    public function businessGames()
    {
        return $this->hasMany(BusinessGame::class);
    }

    public function gamePlays()
    {
        return $this->hasMany(GamePlay::class);
    }

    public function leaderboards()
    {
        return $this->hasMany(Leaderboard::class);
    }

    public function tournaments()
    {
        return $this->hasMany(Tournament::class);
    }

    // Helpers
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function hasActiveSubscription(): bool
    {
        // Testing accounts always have access
        if ($this->is_testing_account) {
            return true;
        }

        if ($this->isOnTrial()) {
            return true;
        }

        if (!$this->stripe_subscription_id) {
            return false;
        }

        // Allow active, trialing, and past_due (grace period).
        // null is included for backward compatibility with existing records
        // that don't have subscription_status populated yet.
        return in_array($this->subscription_status, ['active', 'trialing', 'past_due', null]);
    }

    /**
     * Cache for plan features to avoid N+1 queries
     */
    protected static array $planCache = [];

    /**
     * Get this business' plan feature map (DB is canonical).
     *
     * In production we do NOT fall back to config-based plans because it can drift
     * from what billing and UI show (subscription_plans table).
     */
    protected function planFeatures(): array
    {
        $tier = $this->subscription_tier ?? 'starter';

        if (isset(static::$planCache[$tier])) {
            return static::$planCache[$tier];
        }

        $plan = SubscriptionPlan::where('slug', $tier)->first();
        $features = $plan?->features;

        if (is_array($features) && !empty($features)) {
            static::$planCache[$tier] = $features;
            return $features;
        }

        // Non-production fallback only (backwards compatibility for dev/test environments)
        if (!app()->environment('production')) {
            $fallback = config("plans.{$tier}.features", []);
            $features = is_array($fallback) ? $fallback : [];
            static::$planCache[$tier] = $features;
            return $features;
        }

        static::$planCache[$tier] = [];
        return [];
    }

    public function canAccess(string $feature): bool
    {
        $features = $this->planFeatures();
        $value = $features[$feature] ?? false;

        if (is_bool($value)) {
            return $value;
        }

        // Treat numeric feature flags/limits as accessible if non-zero (or unlimited -1)
        if (is_int($value) || is_float($value)) {
            return ((float) $value) !== 0.0;
        }

        return (bool) $value;
    }

    public function getLimit(string $limit): int
    {
        $features = $this->planFeatures();

        // Limits are stored in the plan's features array (qr_codes, promotions, scans_per_month, etc.)
        $value = $features[$limit] ?? null;

        if ($value === null && !app()->environment('production')) {
            // Legacy config fallback for dev/test only
            $planLimits = config("plans.{$this->subscription_tier}.limits", []);
            $value = $planLimits[$limit] ?? 0;
        }

        if ($value === -1 || $value === '-1') {
            return -1;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return (int) ($value ?? 0);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : null;
    }

    // Onboarding helpers
    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function getOnboardingProgress(): array
    {
        $onboardingService = new \App\Services\OnboardingService();
        // getStepsForBusiness now only returns steps available on the current tier
        $steps = $onboardingService->getStepsForBusiness($this);
        
        $completed = 0;
        $total = count($steps);
        
        foreach ($steps as $step) {
            if ($step['is_completed'] ?? false) {
                $completed++;
            }
        }

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
        ];
    }

    public function completeOnboardingStep(string $stepId): void
    {
        $steps = $this->onboarding_steps ?? [];
        if (!in_array($stepId, $steps)) {
            $steps[] = $stepId;
            $this->update(['onboarding_steps' => $steps]);
        }

        // Check if all available steps are completed
        $progress = $this->getOnboardingProgress();
        if ($progress['completed'] >= $progress['total']) {
            $this->update(['onboarding_completed_at' => now()]);
        }
    }

    public function checkStepCompletion(array $step): bool
    {
        // Check if step is already marked as completed in onboarding_steps
        $completedSteps = $this->onboarding_steps ?? [];
        if (in_array($step['id'], $completedSteps)) {
            return true;
        }

        // If no check callback, return false
        if (!isset($step['check']) || !is_callable($step['check'])) {
            return false;
        }

        try {
            return $step['check']($this);
        } catch (\Exception $e) {
            // If callback fails, return false
            return false;
        }
    }

    public function shouldShowOnboarding(): bool
    {
        if ($this->onboarding_completed_at !== null) {
            return false;
        }

        return !$this->onboarding_dismissed;
    }
}

