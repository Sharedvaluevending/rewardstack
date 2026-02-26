<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Services\CrmSendGridService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request, CrmSendGridService $sendGrid)
    {
        $business = $request->user()->business;

        return Inertia::render('Business/CRM/Settings', [
            'sendGridConfigured' => $sendGrid->isConfigured(),
            'sender' => [
                'from_name' => config('app.name', 'Revenue QR'),
                'from_email' => config('mail.from.address'),
                'reply_to' => $business->email,
            ],
            'webhook' => [
                'signed' => true,
                'endpoint' => url('/webhooks/sendgrid/events'),
            ],
        ]);
    }
}

