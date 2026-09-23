<?php

/*
 * Writing a game for a teacher.
 *
 * Which model writes it was measured rather than guessed: see the builder's
 * scripts/model-bakeoff.js, which scores candidates with this product's own
 * validators and audits. Gemini 3 Flash and GPT-5.4 were the two that broke no
 * rules; Gemini is a twentieth of the cost and three times faster, so it
 * writes and GPT catches it when Google is down.
 */
return [
    'primary' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        'model' => env('AI_MODEL', 'gemini-3-flash-preview'),
        // Per million tokens, for charging a credit what its writing cost.
        'input_per_m' => (float) env('AI_INPUT_PER_M', 0.25),
        'output_per_m' => (float) env('AI_OUTPUT_PER_M', 1.50),
    ],

    // Used only when the primary fails outright — an error, a timeout, or
    // nothing usable. A pack that generates but reads poorly is the teacher's
    // to edit, not ours to second-guess.
    'fallback' => [
        'provider' => env('AI_FALLBACK_PROVIDER', 'chatgpt'),
        'model' => env('AI_FALLBACK_MODEL', 'gpt-5.4'),
        'input_per_m' => (float) env('AI_FALLBACK_INPUT_PER_M', 1.25),
        'output_per_m' => (float) env('AI_FALLBACK_OUTPUT_PER_M', 10.0),
    ],

    /*
     * What one credit's writing may cost us, in cents — generation and every
     * later "ask AI" edit together. It lives on the credit rather than the
     * game so that deleting a draft and starting again draws on the same
     * budget.
     */
    'cents_per_credit' => (int) env('AI_CENTS_PER_CREDIT', 50),

    // Shown to a teacher only past this much of it, so most never learn there
    // is a limit at all.
    'meter_from_percent' => (int) env('AI_METER_FROM_PERCENT', 75),

    /*
     * The day's spend across the whole site, in cents: a fuse, not a policy.
     * The warning is for us — a surge of real buyers looks like this too — and
     * the stop is the last resort. A stopped generation costs the teacher
     * nothing: no credit, no budget, and writing by hand still works.
     */
    'daily_warn_cents' => (int) env('AI_DAILY_WARN_CENTS', 500),
    'daily_stop_cents' => (int) env('AI_DAILY_STOP_CENTS', 2000),

    'keys' => [
        'gemini' => env('GEMINI_API_KEY', ''),
        'chatgpt' => env('OPENAI_API_KEY', ''),
    ],
];
