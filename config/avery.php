<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Avery Design & Print Online (DPO)
    |--------------------------------------------------------------------------
    |
    | We use Avery's "Direct Merge POST" flow to send users to Avery with:
    | - consumer (your provider ID)
    | - deploymentId (US_en / CA_en / CA_fr)
    | - averyBundleUrl (public URL to our hosted .avery bundle)
    | - mergeData + mergeDataFormat (CSV recommended)
    */

    // Your Avery "consumer" / provider ID.
    // Default to Avery's native consumer to keep full in-editor tooling enabled.
    'consumer' => env('AVERY_CONSUMER', 'Avery'),

    // Avery deployment / locale.
    // Use US_en because the hosted .avery bundles were exported from avery.com (US).
    // Customers upload images manually so the CA-only QR/Barcode panel is not needed.
    'deployment_id' => env('AVERY_DEPLOYMENT_ID', 'US_en'),

    // Editor profile/mode. Empty = default Avery profile.
    // YouPrint = print-at-home, WePrint = Avery prints & ships.
    'profile' => env('AVERY_PROFILE', ''),

    // Avery DPO Direct Merge endpoint.
    'merge_direct_url' => env('AVERY_MERGE_DIRECT_URL', 'https://services.print.avery.com/dpp/public/v3/dpo/merge/direct'),

    // Optional: where Avery should send the user when they click "Back" inside DPO.
    // If null, we omit it.
    'back_url' => env('AVERY_BACK_URL'),
];

