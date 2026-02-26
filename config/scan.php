<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy Home Redirect Codes
    |--------------------------------------------------------------------------
    |
    | QR codes that, when scanned and not found in the database, redirect to
    | the homepage instead of showing "QR code not found". Used for legacy
    | marketing links. Comma-separated in .env, e.g. LEGACY_HOME_REDIRECT_CODES=AaaAVH8b
    |
    */
    'legacy_home_redirect_codes' => array_filter(
        array_map('trim', explode(',', env('LEGACY_HOME_REDIRECT_CODES', 'AaaAVH8b')))
    ),
];
