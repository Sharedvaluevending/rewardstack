<?php

return [
    /*
    |--------------------------------------------------------------------------
    | XP + Leveling Tuning
    |--------------------------------------------------------------------------
    |
    | The goal is sustainable progression:
    | - XP is primarily driven by real value (amount saved).
    | - Non-monetary XP (scans + games) is capped daily to prevent farming.
    |
    */

    'non_monetary' => [
        // Daily cap across scan+game XP combined.
        'daily_cap' => 250,
    ],

    'scan' => [
        // Awarded only when a NEW scan row is created (not when refreshing an existing scan).
        'xp' => 5,
    ],

    'game' => [
        // Raw per-play XP is derived from score, then clamped.
        // Final award is also constrained by non_monetary.daily_cap.
        'min_per_award' => 10,
        'max_per_award' => 100,
        // Score -> XP scaling (xp ~= score / divisor).
        'score_divisor' => 40,
    ],

    'redemption' => [
        /*
        | XP from redemptions is based on EFFECTIVE SAVINGS (amount saved).
        |
        | Formula (before caps and repeat-diminishing):
        |   xp = base + k * sqrt(savings)
        |
        | This makes $50 not 25× a $2 deal, while still rewarding higher value.
        */
        'base' => 50,
        'k' => 120,
        'max_per_award' => 5000,

        // Repeat redemptions of the same promotion on the same day are discounted.
        // (Prevents farming on multi-use promos.)
        'repeat_factor_same_promo_same_day' => 0.25,
        'repeat_min_xp' => 100,
    ],

    'badge' => [
        // Keep existing behavior: XP = badge_points * multiplier, but route through addXp() for consistent leveling.
        'xp_per_point' => 10,
    ],

    'merch' => [
        // Ambassador merch XP rewards.
        'scan_xp' => 50,
        'redemption_xp' => 250,
    ],
];

