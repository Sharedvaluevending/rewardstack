<?php

namespace App\Services;

use App\Models\Scan;
use App\Models\User;

class ScanService
{
    /**
     * Store an anonymous scan in the session so it can be attached after login/register.
     */
    public function storeAnonymousScan(Scan $scan): void
    {
        $session = session();
        $anonymous = $session->get('anonymous_scans', []);

        $id = (int) $scan->id;
        if ($id <= 0) {
            return;
        }

        // Avoid duplicates
        foreach ($anonymous as $row) {
            if ((int)($row['scan_id'] ?? 0) === $id) {
                return;
            }
        }

        $anonymous[] = [
            'scan_id' => $id,
        ];

        // Keep session small
        if (count($anonymous) > 25) {
            $anonymous = array_slice($anonymous, -25);
        }

        $session->put('anonymous_scans', $anonymous);
    }

    /**
     * Attach any anonymous scans from this session (and optionally the pre-login session id) to the user.
     *
     * Returns the number of scan rows updated.
     */
    public function associateAnonymousScans(User $user, ?string $preLoginSessionId = null): int
    {
        $session = session();
        $anonymous = $session->get('anonymous_scans', []);
        $updated = 0;

        foreach ($anonymous as $row) {
            $scanId = (int)($row['scan_id'] ?? 0);
            if ($scanId <= 0) {
                continue;
            }

            $scan = Scan::find($scanId);
            if ($scan && !$scan->user_id) {
                $scan->update(['user_id' => $user->id]);
                $updated++;
            }
        }

        // Fallback for older flows: attach recent scans by the pre-login session id.
        if (is_string($preLoginSessionId) && trim($preLoginSessionId) !== '') {
            $recentUpdated = Scan::query()
                ->whereNull('user_id')
                ->where('session_id', $preLoginSessionId)
                ->where('scanned_at', '>=', now()->subHours(6))
                ->update(['user_id' => $user->id]);

            $updated += (int) $recentUpdated;
        }

        $session->forget('anonymous_scans');

        return $updated;
    }
}

