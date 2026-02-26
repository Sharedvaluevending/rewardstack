<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CrmSegment;
use App\Models\EmailUnsubscribe;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CrmAudienceService
{
    /**
     * Base audience query: subscribed customers for this business.
     */
    public function subscribedUsersQuery(Business $business): Builder
    {
        return User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereIn('role', ['customer', 'user'])
            ->whereExists(function ($q) use ($business) {
                $q->select(DB::raw(1))
                    ->from('business_customer_subscriptions as bcs')
                    ->whereColumn('bcs.user_id', 'users.id')
                    ->where('bcs.business_id', $business->id)
                    ->whereNotNull('bcs.subscribed_at')
                    ->whereNull('bcs.unsubscribed_at');
            });
    }

    /**
     * Apply a CRM segment definition to the subscribed users query.
     *
     * v1 supports a small set of safe filters:
     * - last_seen_days
     * - min_scans
     * - min_redemptions
     * - min_saved
     * - min_level
     */
    public function applySegment(Business $business, Builder $query, ?CrmSegment $segment): Builder
    {
        if (!$segment || !is_array($segment->definition)) {
            return $query;
        }

        $def = $segment->definition;
        $filters = is_array($def['filters'] ?? null) ? $def['filters'] : [];

        // Join cached business_customers for numeric filters. If missing rows, treat as 0.
        $query->leftJoin('business_customers as bc', function ($join) use ($business) {
            $join->on('bc.user_id', '=', 'users.id')
                ->where('bc.business_id', '=', $business->id);
        })->select('users.*');

        if (!empty($filters['last_seen_days']) && is_numeric($filters['last_seen_days'])) {
            $days = max(1, (int) $filters['last_seen_days']);
            $query->where('bc.last_seen_at', '>=', now()->subDays($days));
        }
        if (isset($filters['min_scans']) && is_numeric($filters['min_scans'])) {
            $query->where(DB::raw('COALESCE(bc.scans_count, 0)'), '>=', (int) $filters['min_scans']);
        }
        if (isset($filters['min_redemptions']) && is_numeric($filters['min_redemptions'])) {
            $query->where(DB::raw('COALESCE(bc.redemptions_count, 0)'), '>=', (int) $filters['min_redemptions']);
        }
        if (isset($filters['min_saved']) && is_numeric($filters['min_saved'])) {
            $query->where(DB::raw('COALESCE(bc.saved_count, 0)'), '>=', (int) $filters['min_saved']);
        }
        if (isset($filters['min_level']) && is_numeric($filters['min_level'])) {
            $query->where(DB::raw('COALESCE(bc.level_at_last_seen, users.level)'), '>=', (int) $filters['min_level']);
        }

        return $query;
    }

    /**
     * Filter out suppressed emails (bounces/spam/unsubscribe).
     */
    public function excludeSuppressed(Business $business, Builder $query): Builder
    {
        return $query->whereNotExists(function ($q) use ($business) {
            $q->select(DB::raw(1))
                ->from('email_unsubscribes as eu')
                ->whereColumn('eu.email', 'users.email')
                ->where(function ($qq) use ($business) {
                    $qq->whereNull('eu.business_id')->orWhere('eu.business_id', $business->id);
                });
        });
    }
}

