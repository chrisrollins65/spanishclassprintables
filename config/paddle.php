<?php

return [
    /*
     * Paddle is the merchant of record: it is the seller to the teacher, it
     * collects the tax, and it invoices them. We hear about a sale through the
     * webhook below and turn it into credits.
     */
    'webhook_secret' => env('PADDLE_WEBHOOK_SECRET', ''),

    // Server-side API, used to start a refund from our own admin screens so
    // that a refund made here and one made in Paddle's dashboard end the same
    // way: Paddle refunds the money and tells us through the same webhook.
    'api_key' => env('PADDLE_API_KEY', ''),
    'api_url' => env('PADDLE_API_URL', 'https://api.paddle.com'),

    // Paddle.js, which opens the checkout over our own page.
    'client_token' => env('PADDLE_CLIENT_TOKEN', ''),
    'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'),

    /*
     * What each price buys, keyed by Paddle price id. The credits are decided
     * here rather than read from the transaction, so a price edited in Paddle's
     * dashboard can never quietly change what a teacher receives.
     */
    'credits' => [
        env('PADDLE_PRICE_SINGLE', 'pri_single') => (int) env('PADDLE_CREDITS_SINGLE', 1),
        env('PADDLE_PRICE_BUNDLE', 'pri_bundle') => (int) env('PADDLE_CREDITS_BUNDLE', 10),
    ],
];
