<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Onboarding QR Business ID
    |--------------------------------------------------------------------------
    |
    | The business that "owns" the platform-wide onboarding QR codes (Join
    | flyer, Business card). Set via ONBOARDING_QR_BUSINESS_ID in .env.
    | If not set, falls back to the first business by ID.
    |
    */
    'qr_business_id' => env('ONBOARDING_QR_BUSINESS_ID') ? (int) env('ONBOARDING_QR_BUSINESS_ID') : null,
];
