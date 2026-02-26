<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook_token' => env('STRIPE_WEBHOOK_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Printful
    |--------------------------------------------------------------------------
    */

    'printful' => [
        'api_key' => env('PRINTFUL_API_KEY'),
        'store_id' => env('PRINTFUL_STORE_ID'),
        'webhook_token' => env('PRINTFUL_WEBHOOK_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI (for AI Insights)
    |--------------------------------------------------------------------------
    */

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DeepSeek AI (for AI Insights)
    |--------------------------------------------------------------------------
    */

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'api_url' => env('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SendGrid (CRM Email + Event Webhook)
    |--------------------------------------------------------------------------
    */

    'sendgrid' => [
        // Used for CRM campaigns/automations via SendGrid v3 API.
        'api_key' => env('SENDGRID_API_KEY'),

        // Base64 public key for Signed Event Webhook verification.
        // Generated in SendGrid UI when enabling Signed Event Webhook.
        'event_webhook_public_key' => env('SENDGRID_EVENT_WEBHOOK_PUBLIC_KEY'),
    ],

];
