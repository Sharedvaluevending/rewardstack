<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clean up duplicate rewards - keep only the first one per user+promotion combination
     */
    public function up(): void
    {
        // Find duplicate rewards (same user_id + promotion_id + status = available/claimed)
        // Keep the oldest one, mark others as expired.
        //
        // NOTE: SQLite does not support GROUP_CONCAT(... ORDER BY ...), so we use
        // a two-step approach there: find duplicate groups, then fetch IDs ordered by created_at.
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $duplicates = DB::table('game_rewards')
                ->select('user_id', 'promotion_id', DB::raw('COUNT(*) as count'))
                ->whereIn('status', ['available', 'claimed'])
                ->whereNotNull('user_id')
                ->whereNotNull('promotion_id')
                ->groupBy('user_id', 'promotion_id')
                ->having('count', '>', 1)
                ->get();

            foreach ($duplicates as $duplicate) {
                $orderedIds = DB::table('game_rewards')
                    ->where('user_id', $duplicate->user_id)
                    ->where('promotion_id', $duplicate->promotion_id)
                    ->whereIn('status', ['available', 'claimed'])
                    ->orderBy('created_at', 'asc')
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all();

                if (count($orderedIds) <= 1) {
                    continue;
                }

                $keepId = array_shift($orderedIds);
                $rewardIds = $orderedIds;

                DB::table('game_rewards')
                    ->whereIn('id', $rewardIds)
                    ->update([
                        'status' => 'expired',
                        'expires_at' => now(),
                    ]);

                \Log::info('Cleaned up duplicate rewards', [
                    'user_id' => $duplicate->user_id,
                    'promotion_id' => $duplicate->promotion_id,
                    'kept_reward_id' => $keepId,
                    'expired_reward_ids' => $rewardIds,
                ]);
            }

            return;
        }

        // MySQL/MariaDB path (supports ORDER BY in GROUP_CONCAT)
        $duplicates = DB::select("
            SELECT
                user_id,
                promotion_id,
                COUNT(*) as count,
                GROUP_CONCAT(id ORDER BY created_at ASC) as reward_ids
            FROM game_rewards
            WHERE status IN ('available', 'claimed')
                AND user_id IS NOT NULL
                AND promotion_id IS NOT NULL
            GROUP BY user_id, promotion_id
            HAVING count > 1
        ");

        foreach ($duplicates as $duplicate) {
            $rewardIds = explode(',', $duplicate->reward_ids);
            
            // Keep the first (oldest) reward, expire the rest
            $keepId = array_shift($rewardIds);
            
            if (!empty($rewardIds)) {
                DB::table('game_rewards')
                    ->whereIn('id', $rewardIds)
                    ->update([
                        'status' => 'expired',
                        'expires_at' => now(),
                    ]);
                
                \Log::info('Cleaned up duplicate rewards', [
                    'user_id' => $duplicate->user_id,
                    'promotion_id' => $duplicate->promotion_id,
                    'kept_reward_id' => $keepId,
                    'expired_reward_ids' => $rewardIds,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Cannot reverse this operation safely
    }
};

