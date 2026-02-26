# XP & Levels System (Current + Planned)

This document describes how user XP and leveling currently work in the codebase, and what we’re changing to make XP sustainable long-term (target: **Level 50 ≈ 24 months** for a highly active user).

## Current leveling curve

User level is derived from total XP using an exponential curve:

- **XP required to reach Level _L_**: `5000 * (L - 1)^1.5`
- **Level 1** starts at **0 XP**
- The system treats **Level 50** as the max level in multiple places

Code references:

- [`app/Models/User.php`](../app/Models/User.php): `getXpForLevel()`, `addXp()`, `getLevelProgress()`
- [`database/migrations/2025_12_29_233740_recalculate_user_levels_based_on_new_xp_formula.php`](../database/migrations/2025_12_29_233740_recalculate_user_levels_based_on_new_xp_formula.php): recalculates `users.level` from `users.xp` (loops up to level 50)

## Current ways a user earns XP

These are the **only** XP award paths found in the backend code:

### 1) Scan XP

- **+10 XP** when a scan creates a **new** `scans` row (not when refreshing the same scan row)

Code reference:
- [`app/Http/Controllers/ScanController.php`](../app/Http/Controllers/ScanController.php): `recordScan()` (`if ($scan->wasRecentlyCreated) { $user?->addXp(10); }`)

### 2) Promotion redemption XP (staff redemption flow)

- **+25 XP** for **non-punch-card** redemption
- **+25 XP** for **punch card completion** (`card_completed === true`)

Code reference:
- [`app/Http/Controllers/Employee/RedemptionController.php`](../app/Http/Controllers/Employee/RedemptionController.php): `redeem()`

### 3) Game XP

XP is awarded from game play score with anti-farming gating:

- XP amount: `min(100, score)` (or `10` if score <= 0)
- Awarded only if:
  - it’s the **first play for that game+business today**, OR
  - the play is a **new personal best**

Code reference:
- [`app/Models/User.php`](../app/Models/User.php): `recordGamePlay()`

### 4) GameReward redemption XP (reward code redemption flow)

- **+25 XP** when a `GameReward` is redeemed

Code reference:
- [`app/Models/GameReward.php`](../app/Models/GameReward.php): `redeem()`

### 5) Badge XP

Badges award XP based on badge points:

- XP amount: `badge.points * 10`

Code reference:
- [`app/Models/Badge.php`](../app/Models/Badge.php): `awardTo()`

Important note:

- Badge XP currently uses `increment('xp', ...)` directly instead of `User::addXp()`, which means:
  - `users.xp` increases immediately
  - but `users.level` may **not** update immediately (until some later `addXp()` call or a recalculation job/migration)
  - this can cause confusing “late” level-ups or mismatched XP vs level in the UI

## Why we’re changing it

We want XP to be:

- **Sustainable**: no one reaches Level 50 in 2 weeks
- **Money-relative**: primarily tied to **amount saved** (not all promos are equal)
- **Still fun**: scans + games contribute to progression, but cannot be farmed to high levels

## Planned change (implementation notes)

We will centralize XP awarding in a service and log XP events per user per day so we can enforce:

- daily caps for non-monetary XP (scans + games)
- diminishing returns for repeated redemptions on the same promotion
- consistent level updates by routing all XP awards through `User::addXp()`

