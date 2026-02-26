<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe API Keys
    |--------------------------------------------------------------------------
    */
    'secret' => env('STRIPE_SECRET'),
    'publishable' => env('STRIPE_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Connect (for Referral Payouts)
    |--------------------------------------------------------------------------
    | When configured, referrers can connect their bank accounts for
    | automatic payouts instead of manual PayPal/Venmo.
    */
    'connect' => [
        'enabled' => env('STRIPE_CONNECT_ENABLED', false),
        'client_id' => env('STRIPE_CONNECT_CLIENT_ID'),

        // Default country for Connect Express accounts (referrers).
        // Canada = CA, United States = US. Keep configurable so we can expand later.
        'default_country' => env('STRIPE_CONNECT_COUNTRY', 'CA'),
        
        // Minimum balance before auto-payout (in dollars)
        'auto_payout_minimum' => env('STRIPE_CONNECT_AUTO_PAYOUT_MIN', 25),
        
        // Payout schedule: 'manual', 'daily', 'weekly', 'monthly'
        'payout_schedule' => env('STRIPE_CONNECT_PAYOUT_SCHEDULE', 'manual'),

        // Currency for Connect transfers (should match your Stripe platform balance currency)
        'currency' => env('STRIPE_CONNECT_CURRENCY', 'cad'),
    ],
];
