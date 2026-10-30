<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Currency
     |--------------------------------------------------------------------------
     |
     | ISO 4217 code for every paid vote. All amounts are stored as integers in
     | the currency's minor unit (pesewas for GHS). Floats are never used.
     |
     */

    'currency' => env('VOTING_CURRENCY', 'GHS'),

    /*
     |--------------------------------------------------------------------------
     | Order Limits
     |--------------------------------------------------------------------------
     |
     | Upper bound on the number of paid votes a single checkout may buy.
     |
     */

    'max_votes_per_order' => (int) env('VOTING_MAX_VOTES_PER_ORDER', 10_000),

    /*
     |--------------------------------------------------------------------------
     | Close Grace Window
     |--------------------------------------------------------------------------
     |
     | Minutes after a category closes during which payments that were started
     | before the close are still credited. After the window they are recorded
     | as "paid_uncredited" for refund handling, and the category may be revealed.
     |
     */

    'close_grace_minutes' => (int) env('VOTING_CLOSE_GRACE_MINUTES', 5),

    /*
     |--------------------------------------------------------------------------
     | Proxy Email Domain
     |--------------------------------------------------------------------------
     |
     | Paystack requires an email address. Voters who do not provide one are
     | sent as anon-{random}@{domain}. Use a domain the platform controls.
     |
     */

    'proxy_email_domain' => env('VOTING_PROXY_EMAIL_DOMAIN', 'voters.votelab.example'),

    /*
     |--------------------------------------------------------------------------
     | Platform Fee
     |--------------------------------------------------------------------------
     |
     | Platform commission in basis points (100 = 1%). Zero until a platform
     | split is agreed; see plan/EXECUTION_LOG.md (D-13).
     |
     */

    'platform_fee_bps' => (int) env('VOTING_PLATFORM_FEE_BPS', 0),

    /*
     |--------------------------------------------------------------------------
     | Paystack Processing Fee
     |--------------------------------------------------------------------------
     |
     | Used to gross up paid votes so the organizer nets the full face value and
     | the voter covers Paystack's fee. Ghana: 1.95% on local cards, mobile money
     | and bank transfer, no cap, no flat fee (Paystack support, 2026-10-30).
     | Amounts are pesewas; set the cap to null for no cap.
     |
     */

    'paystack_fee' => [
        'bps' => (int) env('VOTING_PAYSTACK_FEE_BPS', 195),
        'flat' => (int) env('VOTING_PAYSTACK_FEE_FLAT', 0),
        'cap' => filled(env('VOTING_PAYSTACK_FEE_CAP')) ? (int) env('VOTING_PAYSTACK_FEE_CAP') : null,
    ],

    /*
     |--------------------------------------------------------------------------
     | Payment References
     |--------------------------------------------------------------------------
     |
     | Prefix for the references VoteLab sends to Paystack, and how long the
     | crediting lock for one reference may be held and waited for (seconds).
     |
     */

    'reference_prefix' => env('VOTING_REFERENCE_PREFIX', 'VL'),

    'fulfilment_lock' => [
        'seconds' => (int) env('VOTING_FULFILMENT_LOCK_SECONDS', 30),
        'wait_seconds' => (int) env('VOTING_FULFILMENT_LOCK_WAIT_SECONDS', 5),
    ],

    /*
     |--------------------------------------------------------------------------
     | Leaderboard Cache
     |--------------------------------------------------------------------------
     |
     | Seconds that public and stage leaderboard reads are cached for, so that
     | polling clients never hit the database on every request.
     |
     */

    'leaderboard_cache_seconds' => (int) env('VOTING_LEADERBOARD_CACHE_SECONDS', 3),
];
