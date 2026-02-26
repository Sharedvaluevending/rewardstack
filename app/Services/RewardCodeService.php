<?php

namespace App\Services;

use App\Models\GameReward;
use App\Models\UserPromoToken;

class RewardCodeService
{
    /**
     * Reward code format: UP-XXXX-XXXX (avoids O/0 and I/1)
     */
    public function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $part1 = $this->randFromAlphabet($alphabet, 4);
            $part2 = $this->randFromAlphabet($alphabet, 4);
            $code = 'UP-' . $part1 . '-' . $part2;
        } while (
            UserPromoToken::where('code', $code)->exists()
            || GameReward::where('reward_code', $code)->exists()
        );

        return $code;
    }

    protected function randFromAlphabet(string $alphabet, int $len): string
    {
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
