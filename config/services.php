<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // The TpT builder (a separate local app) pushes finished Pinterest pin
    // images to /api/internal/pin-asset so Pinterest's bulk uploader has a
    // public URL to fetch them from. Its own secret, not the room one: the key
    // lives in another codebase and should open only this door. Uploads are
    // swept after 'retention_days', which must comfortably outlast the gap
    // between pushing an image and importing the CSV that references it.
    // Same keys as Eat Well Planner's, where these pins were hosted first.
    'pin_assets' => [
        'secret' => env('PIN_ASSET_SECRET'),
        'retention_days' => env('PIN_ASSET_RETENTION_DAYS', 14),
    ],

    // Cloudflare Turnstile on the homepage contact form. Off unless both keys
    // are set; the form's other spam checks work without it. The same variable
    // names as Eat Well Planner's, but a widget's keys only work on the
    // hostnames it lists, so add this site's hostname there or make a new one.
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

];
