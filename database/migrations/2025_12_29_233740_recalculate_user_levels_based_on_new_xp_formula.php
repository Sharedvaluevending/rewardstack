<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Calculate XP required for a level using new formula
     */
    private function getXpForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0; // Level 1 starts at 0 XP
        }
        // Exponential curve: 5000 * (level - 1)^1.5
        return (int) (5000 * pow($level - 1, 1.5));
    }

    /**
     * Calculate what level a user should be at based on their XP
     */
    private function calculateLevelFromXp(int $xp): int
    {
        if ($xp <= 0) {
            return 1;
        }

        // Find the highest level where user's XP >= required XP
        for ($level = 1; $level <= 50; $level++) {
            $requiredXp = $this->getXpForLevel($level + 1);
            if ($xp < $requiredXp) {
                return $level;
            }
        }

        return 50; // Max level
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Recalculate all user levels based on their current XP using the new formula
        $users = DB::table('users')->whereNull('deleted_at')->get();

        foreach ($users as $user) {
            $currentXp = $user->xp ?? 0;
            $newLevel = $this->calculateLevelFromXp($currentXp);

            DB::table('users')
                ->where('id', $user->id)
                ->update(['level' => $newLevel]);
        }
    }

    /**
     * Reverse the migrations.
     * Note: We cannot perfectly reverse this as we don't know the original levels
     */
    public function down(): void
    {
        // Cannot perfectly reverse - would need to store original levels
        // For now, set all users back to level 1
        DB::table('users')->whereNull('deleted_at')->update(['level' => 1]);
    }
};
