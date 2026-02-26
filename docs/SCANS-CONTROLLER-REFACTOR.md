# Scans Controller Refactor Plan

## Overview

`PortalScanController::index()` is ~1750 lines in a single method. It runs 30+ database queries and performs database writes during a GET request. This document outlines what needs to be fixed, why, and in what order.

**Priority:** Plan before next growth push. Not urgent at low traffic, but will be the first bottleneck at scale.

**When to worry:** 500+ concurrent portal users, or power users with 500+ scans/promotions.

---

## Problem 1: Database Writes in a GET Request (Fix First)

The `index()` method (a GET request) performs multiple write operations:

- `QRCode::create(...)` — creates new QR code records
- `SavedQRCode::firstOrCreate(...)` — creates saved QR code records
- `$this->userPromoTokenService->ensure(...)` — creates UserPromoToken records
- `PunchCard::whereIn(...)...->update(...)` — updates punch card records
- `PunchCard::firstOrCreate(...)` — creates punch card records
- `$punchCardRecord->forceFill(...)->saveQuietly()` — updates punch card records

### Why it matters

- Browser retries (timeout, back button, refresh) re-trigger writes
- Opening the page in multiple tabs runs writes concurrently
- Violates HTTP semantics (GET should be idempotent)
- Adds latency to page load — user waits for writes to finish before seeing content

### Fix approach

- Move `ensure()` / `firstOrCreate()` calls to dedicated POST endpoints called on-demand (e.g., when user clicks "Save" or "View Promo")
- Or dispatch as background jobs after page render
- PunchCard sync could be a scheduled job or triggered on scan/redemption instead of on page view

**Estimated effort:** 1-2 hours

---

## Problem 2: N+1 and Redundant Queries (Fix Second)

Several query patterns inside the `transform` / `map` loops fire per-row:

- `QRCodeGame::query()->where(...)` per scan (game reward backfill)
- `Promotion::find($promotionId)` per scan
- `QRCode::query()->where(...)` per scan
- `Redemption::where(...)` per game reward (gameRewardRedemptionIds)
- `PunchCard::where(...)` per token (punch card loop)
- `$scanCountsByQr` is computed but never used (wasted query — already removed in this session)

### Fix approach

- Batch-load QRCodeGame, Promotion, and QRCode records by IDs before the loop
- Replace per-row Redemption queries with a single join-based query
- Pre-load punch card data in bulk before the loop

**Estimated effort:** 2-3 hours

---

## Problem 3: No Pagination on Scans (Fix Third)

All scans are loaded at once:

```php
$scans = Scan::where('user_id', $user->id)->get();
```

For a power user with 500+ scans, this loads everything into memory and runs all the transform logic on every row.

### Fix approach

- Paginate scans (e.g., 20-50 per page)
- Lazy-load secondary data (punch cards, saved promos) via separate Inertia partial reloads or AJAX calls
- Add cursor-based pagination for infinite scroll if desired

**Estimated effort:** 1-2 hours

---

## Problem 4: Method is ~1750 Lines (Cleanup Last)

The entire page assembly lives in one method. This makes it hard to debug, test, or modify safely.

### Fix approach

- Extract into a `PortalScanService` with focused methods:
  - `getScansForUser(User $user, array $filters): LengthAwarePaginator`
  - `getSavedPromotions(User $user, array $filters): LengthAwarePaginator`
  - `getPunchCards(User $user): Collection`
  - `getStats(User $user): array`
  - `getBusinessFilters(User $user): array`
- Controller becomes a thin orchestrator calling the service
- Each method can be independently tested and cached

**Estimated effort:** 3-4 hours

---

## Recommended Execution Order

| Phase | What | Impact | Effort |
|-------|------|--------|--------|
| 1 | Move writes out of GET | Correctness + speed | 1-2h |
| 2 | Fix N+1 queries | Speed (biggest DB savings) | 2-3h |
| 3 | Add pagination | Memory + speed for power users | 1-2h |
| 4 | Extract to service class | Maintainability | 3-4h |

**Total estimated effort:** 7-11 hours across all phases.

Phases can be done independently. Phase 1 is the most important for correctness. Phases 2-3 are the most important for performance. Phase 4 is cleanup.

---

## Quick Wins (Can Do Anytime)

- **Cache stats** (`total_scans`, `total_savings`, etc.) — these don't change on every page view. Cache for 60 seconds.
- **Remove dead queries** — `$scanCountsByQr` was already removed in this session.
- **Add database indexes** if missing on frequently filtered columns (`user_id + qr_code_id`, `user_id + promotion_id` on scans/redemptions).

---

*Last updated: Feb 8, 2026*
