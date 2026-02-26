<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Markup Percentage
    |--------------------------------------------------------------------------
    |
    | The percentage markup added to Printful's cost price.
    | This covers payment processing fees (Stripe ~3%) plus your profit.
    |
    | Example: 15% markup on a $12.95 shirt = $14.89 price to business
    |
    */
    'markup_percent' => env('MERCH_MARKUP_PERCENT', 15),

    /*
    |--------------------------------------------------------------------------
    | Suggested Retail Markup
    |--------------------------------------------------------------------------
    |
    | The suggested markup for businesses who want to resell merch.
    | This is displayed as a helpful suggestion, not enforced.
    |
    | Example: 30% above your price = good margin for business
    |
    */
    'suggested_retail_markup' => env('MERCH_RETAIL_MARKUP', 30),

    /*
    |--------------------------------------------------------------------------
    | Minimum Order
    |--------------------------------------------------------------------------
    |
    | Minimum order value before checkout is allowed.
    | Set to 0 to allow any order size.
    |
    */
    'minimum_order' => env('MERCH_MINIMUM_ORDER', 0),

    /*
    |--------------------------------------------------------------------------
    | Free Shipping Threshold
    |--------------------------------------------------------------------------
    |
    | Order subtotal required for free shipping.
    | Set to null to never offer free shipping.
    |
    */
    'free_shipping_threshold' => env('MERCH_FREE_SHIPPING', null),

    /*
    |--------------------------------------------------------------------------
    | Default Store Business
    |--------------------------------------------------------------------------
    |
    | When no business_id is selected, the admin merch page uses this as the
    | default store. Prefer ID (stable when renaming); name is fallback.
    | Set both to null/empty to use the first business alphabetically.
    |
    */
    'default_store_business_id' => env('MERCH_DEFAULT_STORE_BUSINESS_ID') ? (int) env('MERCH_DEFAULT_STORE_BUSINESS_ID') : null,
    'default_store_business_name' => env('MERCH_DEFAULT_STORE_BUSINESS', 'shared value vending'),
];
