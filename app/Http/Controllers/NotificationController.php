<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
            $this->forgetNotificationCache($user->id);
        }
        return back();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        $this->forgetNotificationCache($request->user()->id);
        return back();
    }

    private function forgetNotificationCache(int $userId): void
    {
        Cache::forget('inertia.unread_notifications.' . $userId);
        Cache::forget('inertia.notifications.' . $userId);
        Cache::forget('inertia.portal_badges.' . $userId);
    }
}
