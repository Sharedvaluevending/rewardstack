<?php

namespace App\Console\Commands;

use App\Models\GameReward;
use App\Notifications\PortalRewardExpiringSoon;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class NotifyExpiringRewards extends Command
{
    protected $signature = 'rewards:notify-expiring {--days=3 : Notify when rewards expire within N days}';

    protected $description = 'Notify users when rewards are about to expire';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $days = $days > 0 ? $days : 3;

        $now = now();
        $end = $now->copy()->addDays($days)->endOfDay();

        $rewards = GameReward::query()
            ->whereNotNull('user_id')
            ->whereIn('status', [GameReward::STATUS_AVAILABLE, GameReward::STATUS_CLAIMED])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $end])
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->with(['user'])
            ->get();

        $notified = 0;

        foreach ($rewards as $reward) {
            $user = $reward->user;
            if (!$user || !in_array($user->role, ['user', 'customer'], true)) {
                continue;
            }

            $already = DatabaseNotification::query()
                ->where('notifiable_type', $user::class)
                ->where('notifiable_id', $user->id)
                ->where('type', PortalRewardExpiringSoon::class)
                ->where('data', 'like', '%"reward_id":' . $reward->id . '%')
                ->exists();

            if ($already) {
                continue;
            }

            $daysLeft = max(1, (int) $now->diffInDays($reward->expires_at, false));
            $user->notify(new PortalRewardExpiringSoon($reward, $daysLeft));
            $notified++;
        }

        $this->info("Notified {$notified} expiring rewards.");
        return 0;
    }
}
