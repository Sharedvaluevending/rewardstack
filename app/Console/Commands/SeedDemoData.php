<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Game;
use App\Models\GamePlay;
use App\Models\GameSession;
use App\Models\Leaderboard;
use App\Models\PunchCard;
use App\Models\Promotion;
use App\Models\QRCode;
use App\Models\QRCodeGame;
use App\Models\Redemption;
use App\Models\SavedQRCode;
use App\Models\StackableEntry;
use App\Models\StackablePool;
use App\Models\UserBadge;
use App\Models\UserPromoToken;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedDemoData extends Command
{
    protected $signature = 'demo:seed
        {--business_ids= : Comma-separated business IDs}
        {--business_names= : Comma-separated business names (exact match preferred)}
        {--days=90 : Spread activity across the last N days}
        {--promotions=20 : Promotions per business}
        {--qr_codes=40 : QR codes per business}
        {--scans=8000 : Scans per business}
        {--plays=2500 : Game plays per business}
        {--prizes=500 : Promo tokens (issued prizes) per business}
        {--redemptions=350 : Redemptions per business (drives finance/savings/punch-card stats)}
        {--saved=120 : Saved QR codes per business (distributed across users)}
        {--badges=30 : User badge awards per business (distributed across users)}
        {--partnerships : Seed partnerships between target businesses}
        {--deal_pools : Seed deal pools and pool entries for target businesses}
        {--create_bigfluff_business : If Bigfluff business is missing, create it for the BIG FLUFF user}
        {--force : Run without confirmation}';

    protected $description = 'Seed realistic demo activity (promos, QR codes, scans, game plays, leaderboards) for specific businesses while preserving existing accounts.';

    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will CREATE demo data (promos, QR codes, scans, plays) for selected businesses. Continue?')) {
                $this->info('Cancelled.');
                return self::SUCCESS;
            }
        }

        $days = max(1, (int) $this->option('days'));
        $promotionsPerBusiness = max(0, (int) $this->option('promotions'));
        $qrPerBusiness = max(0, (int) $this->option('qr_codes'));
        $scansPerBusiness = max(0, (int) $this->option('scans'));
        $playsPerBusiness = max(0, (int) $this->option('plays'));
        $prizesPerBusiness = max(0, (int) $this->option('prizes'));
        $redemptionsPerBusiness = max(0, (int) $this->option('redemptions'));
        $savedPerBusiness = max(0, (int) $this->option('saved'));
        $badgesPerBusiness = max(0, (int) $this->option('badges'));

        $businesses = $this->resolveBusinesses();
        if ($businesses->isEmpty()) {
            $this->error('No businesses found to seed. Provide --business_ids or --business_names.');
            return self::FAILURE;
        }

        // Use existing users only (platform:reset keeps users), so we avoid creating “demo customers”.
        $users = User::query()->orderBy('id')->get(['id', 'name', 'email']);
        if ($users->isEmpty()) {
            $this->error('No users exist to attribute activity to.');
            return self::FAILURE;
        }

        $games = Game::query()->whereIn('id', [6, 5, 3, 1, 4, 2])->get(['id', 'name']); // prefer common games if present
        if ($games->isEmpty()) {
            $games = Game::query()->limit(5)->get(['id', 'name']);
        }

        $this->info("Seeding demo data across last {$days} days for {$businesses->count()} business(es)...");

        // Optional: partnerships / pools (needs both businesses available)
        if ($businesses->count() >= 2) {
            if ($this->option('partnerships')) {
                $this->seedPartnerships($businesses->values());
            }
            if ($this->option('deal_pools')) {
                $this->seedDealPools($businesses->values());
            }
        }

        foreach ($businesses as $business) {
            $this->newLine();
            $this->info("Business: {$business->name} (ID: {$business->id})");

            DB::beginTransaction();

            try {
                $promotions = $this->seedPromotions($business, $promotionsPerBusiness);
                if ($promotionsPerBusiness <= 0) {
                    $promotions = $business->promotions()->get();
                }

                $qrCodes = $this->seedQRCodes($business, $qrPerBusiness, $promotions, $games);
                if ($qrPerBusiness <= 0) {
                    $qrCodes = $business->qrCodes()->get();
                }

                $this->seedScans($business, $qrCodes, $users, $days, $scansPerBusiness);
                $this->updateQrDenormStats($qrCodes);

                $this->seedGamePlays($business, $qrCodes, $users, $days, $playsPerBusiness);
                $this->seedLeaderboards($business, $qrCodes, $users, $games);

                $this->seedPrizesAndRedemptions($business, $promotions, $qrCodes, $users, $days, $prizesPerBusiness, $redemptionsPerBusiness);
                $this->seedSavedQrCodes($business, $qrCodes, $users, $days, $savedPerBusiness);
                $this->seedUserBadges($business, $users, $days, $badgesPerBusiness);
                $this->recalcUserSavingsCounters($business);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Failed seeding {$business->name}: {$e->getMessage()}");
                throw $e;
            }
        }

        $this->newLine();
        $this->info('Done seeding demo data.');
        $this->line('Next recommended commands:');
        $this->line('- php artisan qr-codes:regenerate');
        $this->line('- php artisan analytics:aggregate --days=' . $days . ' --business_id=<id>');
        $this->line('- php artisan promotions:reconcile-stats');

        return self::SUCCESS;
    }

    protected function seedPartnerships($businesses): void
    {
        if (!Schema::hasTable('business_partnerships')) {
            $this->warn('business_partnerships table missing; skipping partnerships seeding.');
            return;
        }

        /** @var \App\Models\Business $a */
        $a = $businesses[0];
        /** @var \App\Models\Business $b */
        $b = $businesses[1];

        $this->info('Seeding partnerships...');

        // 1) Accepted partnership (A -> B)
        DB::table('business_partnerships')->updateOrInsert(
            ['requester_business_id' => $a->id, 'partner_business_id' => $b->id],
            [
                'status' => 'accepted',
                'message' => 'Let’s run a Partner Deal Chain together (demo).',
                'response_message' => 'Accepted (demo).',
                'responded_at' => now()->subDays(7),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(7),
            ]
        );

        // 2) Pending incoming for A (B -> A)
        DB::table('business_partnerships')->updateOrInsert(
            ['requester_business_id' => $b->id, 'partner_business_id' => $a->id],
            [
                'status' => 'pending',
                'message' => 'Partnership request (demo).',
                'response_message' => null,
                'responded_at' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]
        );
    }

    protected function seedDealPools($businesses): void
    {
        if (!Schema::hasTable('stackable_pools') || !Schema::hasTable('stackable_entries')) {
            $this->warn('stackable_pools/stackable_entries tables missing; skipping deal pools seeding.');
            return;
        }

        $this->info('Seeding deal pools...');

        /** @var \App\Models\Business $a */
        $a = $businesses[0];
        /** @var \App\Models\Business $b */
        $b = $businesses[1];

        $pool = StackablePool::firstOrCreate(
            ['code' => 'DEMOPOOL1'],
            [
                'name' => 'Downtown Demo Deal Pool',
                'description' => 'Demo pool to make dashboards look busy.',
                'city' => 'Toronto',
                'region' => 'ON',
                'radius_miles' => 10,
                'requires_approval' => false,
                'created_by' => $a->user_id,
                'is_active' => true,
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->addDays(120),
            ]
        );

        // Pick one promo per business (any active promo)
        $promoA = $a->promotions()->where('is_active', true)->inRandomOrder()->first();
        $promoB = $b->promotions()->where('is_active', true)->inRandomOrder()->first();
        if (!$promoA || !$promoB) {
            return;
        }

        StackableEntry::updateOrCreate(
            ['stackable_pool_id' => $pool->id, 'business_id' => $a->id, 'promotion_id' => $promoA->id],
            [
                'sort_order' => 1,
                'is_featured' => true,
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now()->subDays(20),
            ]
        );

        StackableEntry::updateOrCreate(
            ['stackable_pool_id' => $pool->id, 'business_id' => $b->id, 'promotion_id' => $promoB->id],
            [
                'sort_order' => 2,
                'is_featured' => false,
                'is_active' => true,
                'is_approved' => true,
                'approved_at' => now()->subDays(18),
            ]
        );

        // Add one pending approval entry for business A to show "pending"
        $pendingPromo = $a->promotions()->where('is_active', true)->where('id', '!=', $promoA->id)->inRandomOrder()->first();
        if ($pendingPromo) {
            StackableEntry::updateOrCreate(
                ['stackable_pool_id' => $pool->id, 'business_id' => $a->id, 'promotion_id' => $pendingPromo->id],
                [
                    'sort_order' => 3,
                    'is_featured' => false,
                    'is_active' => true,
                    'is_approved' => false,
                    'approved_at' => null,
                ]
            );
        }
    }

    protected function seedPrizesAndRedemptions(Business $business, $promotions, $qrCodes, $users, int $days, int $prizes, int $redemptions): void
    {
        // Issued prizes: user_promo_tokens
        if ($prizes > 0 && Schema::hasTable('user_promo_tokens')) {
            $this->info("Creating issued prizes (user_promo_tokens): {$prizes}");

            $promoQrs = $qrCodes->filter(fn ($q) => in_array($q->type, ['promotion', 'level_exclusive', 'qrcade'], true) && $q->promotion_id);
            if ($promoQrs->isEmpty()) {
                $promoQrs = $qrCodes->filter(fn ($q) => (bool) $q->promotion_id);
            }

            $userIds = $users->pluck('id')->values()->all();
            $now = now();

            for ($i = 0; $i < $prizes; $i++) {
                $uId = $userIds[array_rand($userIds)];
                $qr = $promoQrs->isNotEmpty() ? $promoQrs->random() : $qrCodes->random();
                $promoId = $qr->promotion_id ?: ($promotions->isNotEmpty() ? $promotions->random()->id : null);
                if (!$promoId) {
                    continue;
                }

                $createdAt = $this->randomTimestamp($now->copy()->subDays($days), $now);
                $redeemIt = rand(1, 100) <= 55;
                $redeemedAt = $redeemIt ? $createdAt->copy()->addDays(rand(0, 21)) : null;

                // Avoid unique(user_id, qr_code_id) collisions by skipping if exists
                $exists = UserPromoToken::where('user_id', $uId)->where('qr_code_id', $qr->id)->exists();
                if ($exists) {
                    continue;
                }

                $token = UserPromoToken::create([
                    'user_id' => $uId,
                    'qr_code_id' => $qr->id,
                    'promotion_id' => $promoId,
                    'business_id' => $business->id,
                    'code' => strtoupper('UP' . Str::random(8)),
                    'redeemed_at' => null,
                    'redemption_id' => null,
                ]);
                $token->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

                if ($redeemedAt) {
                    // Create redemption linked to token (drives finance + "redeemed prizes")
                    $r = $this->createRedemptionForToken($business, $qr, $promoId, $uId, $token->id, $redeemedAt);
                    $token->forceFill([
                        'redeemed_at' => $redeemedAt,
                        'redemption_id' => $r?->id,
                        'updated_at' => $redeemedAt,
                    ])->saveQuietly();
                }
            }

            // Link some tokens to existing game_plays as "game prizes" (dashboard uses game_data._user_promo_token_code)
            $tokenSample = UserPromoToken::where('business_id', $business->id)->inRandomOrder()->limit(120)->get();
            if ($tokenSample->isNotEmpty()) {
                $plays = GamePlay::where('business_id', $business->id)->whereNotNull('user_id')->inRandomOrder()->limit($tokenSample->count())->get();
                foreach ($plays as $idx => $play) {
                    $t = $tokenSample[$idx] ?? null;
                    if (!$t) break;
                    $data = is_array($play->game_data) ? $play->game_data : [];
                    $data['demo'] = true;
                    $data['_user_promo_token_code'] = $t->code;
                    $play->forceFill(['game_data' => $data])->saveQuietly();
                }
            }
        }

        // Finance/Savings/Punch cards: redemptions
        if ($redemptions > 0 && Schema::hasTable('redemptions')) {
            $this->info("Creating redemptions: {$redemptions}");

            $userIds = $users->pluck('id')->values()->all();
            $now = now();

            $punchPromos = $promotions->filter(fn ($p) => $p->discount_type === Promotion::TYPE_PUNCH_CARD);
            $normalPromos = $promotions->filter(fn ($p) => $p->discount_type !== Promotion::TYPE_PUNCH_CARD);

            for ($i = 0; $i < $redemptions; $i++) {
                $isPunch = ($punchPromos->isNotEmpty() && rand(1, 100) <= 25);
                $promo = $isPunch
                    ? $punchPromos->random()
                    : ($normalPromos->isNotEmpty() ? $normalPromos->random() : $promotions->random());

                $qr = $qrCodes->firstWhere('promotion_id', $promo->id) ?? $qrCodes->random();
                $customerUserId = $userIds[array_rand($userIds)];
                $redeemedAt = $this->randomTimestamp($now->copy()->subDays($days), $now);

                $original = rand(8, 120);
                $discount = $isPunch ? rand(0, 5) : rand(2, min(25, $original - 1));
                $final = max(0, $original - $discount);

                $punchesAdded = null;
                $cardCompleted = false;
                if ($isPunch) {
                    $punchesAdded = rand(1, 2);
                    $cardCompleted = rand(1, 100) <= 18;
                }

                $r = Redemption::create([
                    'promotion_id' => $promo->id,
                    'qr_code_id' => $qr?->id,
                    'business_id' => $business->id,
                    'employee_id' => null,
                    'redeemed_by_user_id' => $business->user_id,
                    'scan_id' => null,
                    'customer_user_id' => $customerUserId,
                    'user_promo_token_id' => null,
                    'customer_identifier' => null,
                    'customer_name' => null,
                    'customer_email' => null,
                    'customer_phone' => null,
                    'original_amount' => $original,
                    'discount_amount' => $discount,
                    'final_amount' => $final,
                    'punches_added' => $punchesAdded,
                    'card_completed' => $cardCompleted,
                    'notes' => $isPunch ? 'Punch card stamp (demo)' : 'Redemption (demo)',
                    'latitude' => null,
                    'longitude' => null,
                    'redeemed_at' => $redeemedAt,
                ]);
                $r->forceFill(['created_at' => $redeemedAt, 'updated_at' => $redeemedAt])->saveQuietly();

                if ($isPunch && Schema::hasTable('punch_cards')) {
                    $this->applyPunchCardProgress($promo->id, $customerUserId, $punchesAdded ?? 1, $cardCompleted, $redeemedAt);
                }
            }
        }
    }

    protected function createRedemptionForToken(Business $business, QRCode $qr, int $promotionId, int $customerUserId, int $tokenId, Carbon $redeemedAt): ?Redemption
    {
        if (!Schema::hasTable('redemptions')) {
            return null;
        }

        $original = rand(10, 80);
        $discount = rand(2, min(20, $original - 1));
        $final = max(0, $original - $discount);

        $r = Redemption::create([
            'promotion_id' => $promotionId,
            'qr_code_id' => $qr->id,
            'business_id' => $business->id,
            'employee_id' => null,
            'redeemed_by_user_id' => $business->user_id,
            'scan_id' => null,
            'customer_user_id' => $customerUserId,
            'user_promo_token_id' => $tokenId,
            'customer_identifier' => null,
            'customer_name' => null,
            'customer_email' => null,
            'customer_phone' => null,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'final_amount' => $final,
            'punches_added' => null,
            'card_completed' => false,
            'notes' => 'Redeemed prize token (demo)',
            'latitude' => null,
            'longitude' => null,
            'redeemed_at' => $redeemedAt,
        ]);
        $r->forceFill(['created_at' => $redeemedAt, 'updated_at' => $redeemedAt])->saveQuietly();

        return $r;
    }

    protected function applyPunchCardProgress(int $promotionId, int $customerUserId, int $punchesAdded, bool $cardCompleted, Carbon $when): void
    {
        $identifier = 'user:' . $customerUserId;
        $pc = PunchCard::firstOrCreate(
            ['promotion_id' => $promotionId, 'customer_identifier' => $identifier],
            ['punches' => 0, 'completed_cards' => 0, 'last_punch_at' => null]
        );

        $pc->punches = (int) $pc->punches + max(0, $punchesAdded);
        if ($cardCompleted) {
            $pc->completed_cards = (int) $pc->completed_cards + 1;
        }
        $pc->last_punch_at = $when;
        $pc->saveQuietly();
    }

    protected function seedSavedQrCodes(Business $business, $qrCodes, $users, int $days, int $count): void
    {
        if ($count <= 0 || !Schema::hasTable('saved_qr_codes')) {
            return;
        }

        $this->info("Creating saved QR codes: {$count}");

        $userIds = $users->pluck('id')->values()->all();
        $qrIds = $qrCodes->pluck('id')->values()->all();
        if (empty($qrIds)) {
            return;
        }

        $now = now();
        $attempts = 0;
        $created = 0;
        while ($created < $count && $attempts < ($count * 5)) {
            $attempts++;
            $uId = $userIds[array_rand($userIds)];
            $qrId = $qrIds[array_rand($qrIds)];
            $savedAt = $this->randomTimestamp($now->copy()->subDays($days), $now);

            try {
                SavedQRCode::create([
                    'user_id' => $uId,
                    'qr_code_id' => $qrId,
                    'saved_at' => $savedAt,
                ])->forceFill(['created_at' => $savedAt, 'updated_at' => $savedAt])->saveQuietly();
                $created++;
            } catch (\Throwable $e) {
                // Ignore duplicates (unique constraint)
            }
        }
    }

    protected function seedUserBadges(Business $business, $users, int $days, int $count): void
    {
        if ($count <= 0 || !Schema::hasTable('user_badges') || !Schema::hasTable('badges')) {
            return;
        }

        $badgeIds = DB::table('badges')->where('is_active', 1)->pluck('id')->all();
        if (empty($badgeIds)) {
            return;
        }

        $this->info("Creating user badges: {$count}");

        $userIds = $users->pluck('id')->values()->all();
        $now = now();
        $attempts = 0;
        $created = 0;

        while ($created < $count && $attempts < ($count * 6)) {
            $attempts++;
            $uId = $userIds[array_rand($userIds)];
            $bId = $badgeIds[array_rand($badgeIds)];
            $earnedAt = $this->randomTimestamp($now->copy()->subDays($days), $now);

            try {
                UserBadge::create([
                    'user_id' => $uId,
                    'badge_id' => $bId,
                    'business_id' => rand(1, 100) <= 65 ? $business->id : null,
                    'game_play_id' => null,
                    'progress' => rand(0, 100),
                    'progress_max' => 100,
                    'is_complete' => true,
                    'earned_at' => $earnedAt,
                    'is_featured' => rand(1, 100) <= 10,
                    'is_new' => false,
                ])->forceFill(['created_at' => $earnedAt, 'updated_at' => $earnedAt])->saveQuietly();
                $created++;
            } catch (\Throwable $e) {
                // Ignore duplicates due to unique constraint
            }
        }
    }

    protected function recalcUserSavingsCounters(Business $business): void
    {
        // Portal uses users.total_savings, so keep it looking alive.
        if (!Schema::hasTable('redemptions') || !Schema::hasColumn('users', 'total_savings')) {
            return;
        }

        $rows = DB::table('redemptions')
            ->where('business_id', $business->id)
            ->whereNotNull('customer_user_id')
            ->selectRaw('customer_user_id as user_id, COALESCE(SUM(discount_amount),0) as savings')
            ->groupBy('customer_user_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('users')->where('id', $row->user_id)->update([
                'total_savings' => (float) $row->savings,
            ]);
        }
    }

    protected function resolveBusinesses()
    {
        $idsOpt = trim((string) $this->option('business_ids'));
        $namesOpt = trim((string) $this->option('business_names'));

        $ids = [];
        if ($idsOpt !== '') {
            $ids = collect(explode(',', $idsOpt))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();
        }

        $names = [];
        if ($namesOpt !== '') {
            $names = collect(explode(',', $namesOpt))
                ->map(fn ($v) => trim($v))
                ->filter()
                ->values()
                ->all();
        }

        if (empty($ids) && empty($names)) {
            // Default for your demo video use-case
            $names = ['Shared Value Vending', 'Bigfluff'];
        }

        $businesses = Business::query();
        if (!empty($ids)) {
            $businesses->whereIn('id', $ids);
        }

        if (!empty($names)) {
            $businesses->orWhere(function ($q) use ($names) {
                foreach ($names as $name) {
                    $q->orWhereRaw('LOWER(name) = ?', [strtolower($name)]);
                }
            });
        }

        $result = $businesses->get();

        // Optional: create Bigfluff business if missing (owner is existing user BIG FLUFF)
        if ($this->option('create_bigfluff_business')) {
            $needsBigfluff = !Business::query()->whereRaw('LOWER(name) = ?', ['bigfluff'])->exists();
            if ($needsBigfluff) {
                $owner = User::query()
                    ->whereRaw('LOWER(name) = ?', ['big fluff'])
                    ->orWhereRaw('LOWER(email) = ?', ['xrpmekal@gmail.com'])
                    ->first();

                if ($owner) {
                    $slugBase = Str::slug('Bigfluff');
                    $slug = $slugBase;
                    $i = 2;
                    while (Business::where('slug', $slug)->exists()) {
                        $slug = "{$slugBase}-{$i}";
                        $i++;
                    }

                    $bigfluff = Business::create([
                        'user_id' => $owner->id,
                        'name' => 'Bigfluff',
                        'slug' => $slug,
                        'type' => 'retail',
                        'description' => 'Demo business for screenshots/video.',
                        'country' => 'CA',
                        'primary_color' => '#10b981',
                        'secondary_color' => '#8b5cf6',
                        'subscription_tier' => 'enterprise',
                        'trial_ends_at' => now()->addYears(5),
                        'is_active' => true,
                        'is_testing_account' => true,
                    ]);

                    $this->info("Created missing business: {$bigfluff->name} (ID: {$bigfluff->id}) for user {$owner->name}");
                    $result = $result->push($bigfluff);
                } else {
                    $this->warn('Wanted to create Bigfluff business, but could not find a BIG FLUFF user to own it.');
                }
            }
        }

        return $result->unique('id')->values();
    }

    protected function seedPromotions(Business $business, int $count)
    {
        if ($count <= 0) {
            return collect();
        }

        $types = [
            Promotion::TYPE_PERCENTAGE,
            Promotion::TYPE_FIXED_AMOUNT,
            Promotion::TYPE_BOGO,
            Promotion::TYPE_BUY_X_GET_Y,
            Promotion::TYPE_PUNCH_CARD,
            Promotion::TYPE_HAPPY_HOUR,
            Promotion::TYPE_FIRST_TIME,
            Promotion::TYPE_LOYALTY,
        ];

        $created = collect();
        for ($i = 0; $i < $count; $i++) {
            $type = $types[$i % count($types)];

            $startsAt = now()->subDays(rand(1, 60));
            $endsAt = now()->addDays(rand(7, 120));

            $payload = [
                'business_id' => $business->id,
                'name' => $this->promoName($business, $type, $i),
                'description' => 'Demo promotion for screenshots/video.',
                'terms' => 'Demo terms. Valid while supplies last.',
                'discount_type' => $type,
                'discount_value' => null,
                'buy_quantity' => null,
                'get_quantity' => null,
                'for_price' => null,
                'punches_required' => null,
                'reward_value' => null,
                'punch_icon' => null,
                'tiers' => null,
                'original_price' => null,
                'minimum_purchase' => null,
                'maximum_discount' => null,
                'rules' => [
                    'max_redemptions_total' => rand(50, 500),
                    'max_redemptions_per_user' => rand(0, 1) ? 1 : 0,
                ],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'total_views' => rand(50, 2500),
                'total_redemptions' => rand(5, 250),
                'total_savings' => rand(50, 5000),
                'is_active' => true,
                'is_featured' => (bool) rand(0, 1),
                'is_stackable' => false,
            ];

            switch ($type) {
                case Promotion::TYPE_PERCENTAGE:
                case Promotion::TYPE_HAPPY_HOUR:
                case Promotion::TYPE_FIRST_TIME:
                case Promotion::TYPE_LOYALTY:
                    $payload['discount_value'] = rand(10, 40);
                    break;
                case Promotion::TYPE_FIXED_AMOUNT:
                    $payload['discount_value'] = rand(2, 15);
                    break;
                case Promotion::TYPE_BOGO:
                    $payload['discount_value'] = null;
                    break;
                case Promotion::TYPE_BUY_X_GET_Y:
                    $payload['buy_quantity'] = 2;
                    $payload['get_quantity'] = 1;
                    break;
                case Promotion::TYPE_PUNCH_CARD:
                    $payload['punches_required'] = rand(6, 12);
                    $payload['reward_value'] = rand(5, 20);
                    $payload['punch_icon'] = collect(array_keys(Promotion::punchIconOptions()))->random();
                    break;
            }

            $created->push(Promotion::create($payload));
        }

        $this->info("Created promotions: {$created->count()}");
        return $created;
    }

    protected function seedQRCodes(Business $business, int $count, $promotions, $games)
    {
        if ($count <= 0) {
            return collect();
        }

        $created = collect();

        // Ensure we have at least 1 leaderboard QR + matching leaderboard prize promo.
        $leaderboardPrizePromo = Promotion::create([
            'business_id' => $business->id,
            'name' => 'Weekly Winner Prize',
            'description' => 'Leaderboard winner prize (demo).',
            'terms' => 'Demo prize terms.',
            'discount_type' => Promotion::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'starts_at' => now()->subDays(14),
            'ends_at' => now()->addDays(180),
            'is_active' => true,
            'is_featured' => true,
            'rules' => ['max_redemptions_total' => 9999],
        ]);

        $leaderboardGame = $games->firstWhere('id', 6) ?? $games->first(); // QR Dash if available

        $lbQr = QRCode::create([
            'business_id' => $business->id,
            'name' => 'QRcade Leaderboard Challenge',
            'type' => 'qrcade_leaderboard',
            'intended_use' => QRCode::INTENDED_USE_PUBLIC,
            'promotion_id' => null,
            'design' => $this->randomQrDesign($business, 'leaderboard'),
            'placement_location' => 'counter',
            'placement_description' => 'Leaderboard challenge (demo).',
            'is_active' => true,
            'game_enabled' => true,
        ]);

        QRCodeGame::create([
            'qr_code_id' => $lbQr->id,
            'game_id' => $leaderboardGame->id,
            'business_id' => $business->id,
            'is_active' => true,
            'promotion_id' => null,
            'win_mode' => QRCodeGame::WIN_MODE_LEADERBOARD,
        ]);

        $created->push($lbQr);

        // Create mix of QR code types.
        $types = [
            'promotion',
            'promotion',
            'promotion',
            'static',
            'dynamic',
            'qrcade',
            'qrcade',
            'level_exclusive',
        ];

        for ($i = 0; $i < ($count - 1); $i++) {
            $type = $types[$i % count($types)];
            $promotionId = null;
            $requiredLevel = null;
            $isLevelExclusive = false;

            if (in_array($type, ['promotion', 'level_exclusive'], true)) {
                $promotionId = $promotions->isNotEmpty()
                    ? $promotions->random()->id
                    : $leaderboardPrizePromo->id;
            }

            if ($type === 'level_exclusive') {
                $requiredLevel = rand(2, 8);
                $isLevelExclusive = true;
            }

            $qr = QRCode::create([
                'business_id' => $business->id,
                'name' => $this->qrName($business, $type, $i),
                'type' => $type,
                'intended_use' => QRCode::INTENDED_USE_PUBLIC,
                'destination_url' => $type === 'dynamic' ? 'https://revenueqr.com' : null,
                'promotion_id' => $promotionId,
                'design' => $this->randomQrDesign($business, $type),
                'placement_location' => collect(['window', 'menu', 'counter', 'table', 'flyer', 'sticker', 'poster'])->random(),
                'placement_description' => 'Demo placement.',
                'is_active' => true,
                'required_level' => $requiredLevel,
                'is_level_exclusive' => $isLevelExclusive,
                'game_enabled' => in_array($type, ['qrcade'], true),
            ]);

            // Attach games for qrcade types.
            if ($type === 'qrcade') {
                $game = $games->random();
                $prizePromo = $promotions->isNotEmpty() ? $promotions->random() : $leaderboardPrizePromo;

                QRCodeGame::create([
                    'qr_code_id' => $qr->id,
                    'game_id' => $game->id,
                    'business_id' => $business->id,
                    'is_active' => true,
                    'promotion_id' => $prizePromo->id,
                    'win_mode' => QRCodeGame::WIN_MODE_ALWAYS,
                ]);
            }

            $created->push($qr);
        }

        $this->info("Created QR codes: {$created->count()}");
        return $created;
    }

    protected function seedScans(Business $business, $qrCodes, $users, int $days, int $scanCount): void
    {
        if ($scanCount <= 0 || $qrCodes->isEmpty()) {
            return;
        }

        if (!Schema::hasTable('scans')) {
            $this->warn('scans table missing; skipping scan seeding.');
            return;
        }

        $qrIds = $qrCodes->pluck('id')->values()->all();
        $userIds = $users->pluck('id')->values()->all();

        $this->info("Creating scans: {$scanCount}");

        $rows = [];
        $now = now();

        for ($i = 0; $i < $scanCount; $i++) {
            $qrId = $qrIds[array_rand($qrIds)];
            $qr = $qrCodes->firstWhere('id', $qrId);

            $scannedAt = $this->randomTimestamp($now->copy()->subDays($days), $now);
            $sessionId = Str::random(24);

            $maybeUserId = null;
            if (rand(1, 100) <= 55) {
                $maybeUserId = $userIds[array_rand($userIds)];
                $sessionId = 'u' . $maybeUserId . '-' . Str::random(16);
            }

            $rows[] = [
                'qr_code_id' => $qrId,
                'scan_type' => $this->scanTypeForQr($qr?->type),
                'business_id' => $business->id,
                'user_id' => $maybeUserId,
                'session_id' => $sessionId,
                'ip_address' => '137.220.54.' . rand(2, 254),
                'user_agent' => 'Mozilla/5.0 (Demo Seed)',
                'device_type' => collect(['mobile', 'mobile', 'mobile', 'desktop', 'tablet'])->random(),
                'browser' => collect(['Chrome', 'Safari', 'Firefox'])->random(),
                'os' => collect(['iOS', 'Android', 'Windows', 'macOS'])->random(),
                'latitude' => null,
                'longitude' => null,
                'city' => collect(['Toronto', 'Vancouver', 'Calgary', 'Ottawa', 'Montreal'])->random(),
                'region' => collect(['ON', 'BC', 'AB', 'QC'])->random(),
                'country' => 'CA',
                'referrer' => null,
                'utm_source' => null,
                'utm_medium' => null,
                'utm_campaign' => null,
                'scanned_at' => $scannedAt,
                'created_at' => $scannedAt,
                'updated_at' => $scannedAt,
            ];

            if (count($rows) >= 1000) {
                DB::table('scans')->insert($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            DB::table('scans')->insert($rows);
        }
    }

    protected function seedGamePlays(Business $business, $qrCodes, $users, int $days, int $playsCount): void
    {
        if ($playsCount <= 0) {
            return;
        }

        $gameQrCodes = $qrCodes->filter(fn ($q) => in_array($q->type, ['qrcade', 'qrcade_leaderboard'], true));
        if ($gameQrCodes->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('id')->values()->all();
        $now = now();

        $this->info("Creating game plays: {$playsCount}");

        for ($i = 0; $i < $playsCount; $i++) {
            /** @var \App\Models\QRCode $qr */
            $qr = $gameQrCodes->random();
            $qrGame = QRCodeGame::query()
                ->where('qr_code_id', $qr->id)
                ->where('is_active', true)
                ->inRandomOrder()
                ->first();

            if (!$qrGame) {
                continue;
            }

            $playedAt = $this->randomTimestamp($now->copy()->subDays($days), $now);
            $userId = (rand(1, 100) <= 70) ? $userIds[array_rand($userIds)] : null;

            $session = GameSession::create([
                'user_id' => $userId,
                'qr_code_id' => $qr->id,
                'game_id' => $qrGame->game_id,
                'business_id' => $business->id,
                'location_status' => GameSession::LOCATION_VERIFIED,
                'location_verified_at' => $playedAt,
                'ip_address' => '137.220.54.' . rand(2, 254),
                'user_agent' => 'Mozilla/5.0 (Demo Seed)',
                'device_type' => collect(['mobile', 'desktop', 'tablet'])->random(),
                'status' => GameSession::STATUS_COMPLETED,
                'fun_only' => false,
                'fun_only_reason' => null,
                'started_at' => $playedAt->copy()->subSeconds(rand(10, 120)),
                'expires_at' => $playedAt->copy()->addHour(),
            ]);
            // Eloquent ignores created_at/updated_at in create() payload; force them so analytics can spread over time.
            $session->forceFill(['created_at' => $playedAt, 'updated_at' => $playedAt])->saveQuietly();

            $score = rand(50, 4500);
            $result = (rand(1, 100) <= 45) ? GamePlay::RESULT_WIN : GamePlay::RESULT_LOSE;

            $play = GamePlay::create([
                'game_session_id' => $session->id,
                'user_id' => $userId,
                'game_id' => $qrGame->game_id,
                'business_id' => $business->id,
                'qr_code_id' => $qr->id,
                'score' => $score,
                'duration_seconds' => rand(15, 180),
                'difficulty' => collect(['easy', 'medium', 'hard'])->random(),
                'level_reached' => rand(1, 15),
                'game_data' => ['demo' => true],
                'result' => $result,
                'reward_tier' => null,
                'is_high_score' => false,
                'is_personal_best' => false,
                'is_suspicious' => false,
                'suspicious_reason' => null,
                'started_at' => $session->started_at,
                'completed_at' => $playedAt,
            ]);
            $play->forceFill(['created_at' => $playedAt, 'updated_at' => $playedAt])->saveQuietly();
        }
    }

    protected function seedLeaderboards(Business $business, $qrCodes, $users, $games): void
    {
        $leaderboardQr = $qrCodes->firstWhere('type', 'qrcade_leaderboard');
        $game = $games->firstWhere('id', 6) ?? $games->first();

        if (!$leaderboardQr || !$game) {
            return;
        }

        $lb = Leaderboard::firstOrCreate(
            [
                'business_id' => $business->id,
                'game_id' => $game->id,
                'type' => Leaderboard::TYPE_LOCATION,
                'reset_frequency' => Leaderboard::RESET_WEEKLY,
            ],
            [
                'name' => "{$business->name} Weekly Leaderboard",
                'slug' => Str::slug($business->name . '-weekly-' . $game->name . '-' . $business->id),
                'description' => 'Demo leaderboard for screenshots/video.',
                'reset_day' => 'monday',
                'current_period_start' => now()->startOfWeek(),
                'current_period_end' => now()->endOfWeek(),
                'max_entries' => 100,
                'score_type' => Leaderboard::SCORE_HIGHEST,
                'show_score' => true,
                'show_games_played' => true,
                'prize_config' => ['winners' => 3],
                'qr_code_id' => $leaderboardQr->id,
                'is_active' => true,
            ]
        );

        // Populate leaderboard entries based on existing game plays where possible, otherwise random.
        $periodKey = $lb->getCurrentPeriodKey();

        $topUsers = $users->shuffle()->take(min(10, $users->count()));
        foreach ($topUsers as $u) {
            $best = GamePlay::query()
                ->where('business_id', $business->id)
                ->where('game_id', $game->id)
                ->where('user_id', $u->id)
                ->max('score');

            $score = $best ?: rand(500, 4500);
            $plays = rand(1, 25);
            $wins = rand(0, $plays);

            // Use helper to recalc ranks properly.
            $lb->updateUserScore($u, (int) $score, $plays, $wins > 0);

            // Ensure period_key is set (updateUserScore uses getCurrentPeriodKey already)
            DB::table('leaderboard_entries')
                ->where('leaderboard_id', $lb->id)
                ->where('user_id', $u->id)
                ->update(['period_key' => $periodKey]);
        }

        $this->info('Seeded leaderboard + entries.');
    }

    protected function updateQrDenormStats($qrCodes): void
    {
        if ($qrCodes->isEmpty()) {
            return;
        }

        $ids = $qrCodes->pluck('id')->values()->all();

        $agg = DB::table('scans')
            ->selectRaw('qr_code_id, COUNT(*) as total_scans, COUNT(DISTINCT session_id) as unique_scans, MAX(scanned_at) as last_scanned_at')
            ->whereIn('qr_code_id', $ids)
            ->groupBy('qr_code_id')
            ->get();

        foreach ($agg as $row) {
            QRCode::where('id', $row->qr_code_id)->update([
                'total_scans' => (int) $row->total_scans,
                'unique_scans' => (int) $row->unique_scans,
                'last_scanned_at' => $row->last_scanned_at,
            ]);
        }
    }

    protected function randomTimestamp(Carbon $from, Carbon $to): Carbon
    {
        $fromTs = $from->getTimestamp();
        $toTs = $to->getTimestamp();
        $ts = rand(min($fromTs, $toTs), max($fromTs, $toTs));
        return Carbon::createFromTimestamp($ts);
    }

    protected function scanTypeForQr(?string $qrType): ?string
    {
        return match ($qrType) {
            'qrcade' => \App\Models\Scan::TYPE_QRCADE_GAME,
            'qrcade_leaderboard' => \App\Models\Scan::TYPE_QRCADE_LEADERBOARD,
            'promotion', 'level_exclusive' => \App\Models\Scan::TYPE_PROMOTION,
            'cross_promo' => \App\Models\Scan::TYPE_CROSS_PROMO,
            'stackable' => \App\Models\Scan::TYPE_STACKABLE,
            default => \App\Models\Scan::TYPE_INFO,
        };
    }

    protected function promoName(Business $business, string $type, int $i): string
    {
        $base = match ($type) {
            Promotion::TYPE_PERCENTAGE => rand(10, 40) . '% Off',
            Promotion::TYPE_FIXED_AMOUNT => '$' . rand(2, 15) . ' Off',
            Promotion::TYPE_BOGO => 'BOGO Deal',
            Promotion::TYPE_BUY_X_GET_Y => 'Buy 2 Get 1',
            Promotion::TYPE_PUNCH_CARD => 'Punch Card Rewards',
            Promotion::TYPE_HAPPY_HOUR => 'Happy Hour Special',
            Promotion::TYPE_FIRST_TIME => 'First Time Deal',
            Promotion::TYPE_LOYALTY => 'Loyalty Reward',
            default => 'Special Offer',
        };

        return "{$base} #" . ($i + 1);
    }

    protected function qrName(Business $business, string $type, int $i): string
    {
        return match ($type) {
            'promotion' => 'Promo QR #' . ($i + 1),
            'qrcade' => 'QRcade Game #' . ($i + 1),
            'qrcade_leaderboard' => 'Leaderboard Challenge',
            'level_exclusive' => 'VIP Level QR #' . ($i + 1),
            'static' => 'Info QR #' . ($i + 1),
            'dynamic' => 'Link QR #' . ($i + 1),
            default => 'QR Code #' . ($i + 1),
        };
    }

    protected function randomQrDesign(Business $business, string $context): array
    {
        $bg = collect(['#0b1220', '#0f172a', '#111827', '#ffffff', '#0a0a0a'])->random();
        $module = collect(['#10b981', '#06b6d4', '#8b5cf6', '#f59e0b', '#ef4444', '#111827'])->random();
        $text = ($bg === '#ffffff') ? '#111827' : '#ffffff';

        $topText = match ($context) {
            'promotion' => 'SCAN FOR DEALS',
            'qrcade' => 'PLAY & WIN',
            'leaderboard', 'qrcade_leaderboard' => 'LEADERBOARD',
            'level_exclusive' => 'VIP ACCESS',
            default => 'SCAN ME',
        };

        return [
            'size' => 420,
            'margin' => 12,
            'error_correction' => 'H',
            'module_shape' => collect(['square', 'rounded', 'dots', 'diamond'])->random(),
            'finder_shape' => collect(['square', 'rounded', 'circle'])->random(),
            'background_color' => $bg,
            'module_color' => $module,
            'text_top' => [
                'content' => $topText,
                'font' => 'Inter',
                'size' => 18,
                'color' => $text,
            ],
            'text_bottom' => [
                'content' => 'Revenue QR',
                'font' => 'Inter',
                'size' => 14,
                'color' => $text,
            ],
            'border' => [
                'width' => 6,
                'color' => $module,
                'style' => 'solid',
                'radius' => 18,
            ],
            'glow' => [
                'color' => $module,
                'intensity' => 0.35,
                'spread' => 14,
            ],
            'shadow' => [
                'color' => '#000000',
                'opacity' => 0.35,
                'blur' => 18,
                'offsetX' => 0,
                'offsetY' => 8,
            ],
        ];
    }
}

