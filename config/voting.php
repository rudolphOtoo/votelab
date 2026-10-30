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
     | Paystack Processing Fee (L-3)
     |--------------------------------------------------------------------------
     |
     | Paystack's rate in basis points (195 = 1.95%). The voter pays it on top:
     | total = ceil(vote price / (1 - rate)). Ghana: 1.95% on local cards, mobile
     | money and bank transfer, no cap, no flat fee (Paystack support, 2026-10-30).
     |
     */

    'paystack_fee_bps' => (int) env('VOTING_PAYSTACK_FEE_BPS', 195),

    /*
     |--------------------------------------------------------------------------
     | Settlement (L-4, L-5)
     |--------------------------------------------------------------------------
     |
     | Paid votes settle 100% to the organization's Paystack subaccount, sent as
     | `subaccount` with a main-account `transaction_charge` of 0. There is no
     | platform commission. `bearer` decides who pays Paystack's fee: the
     | subaccount, out of the processing fee the voter paid on top.
     |
     */

    'settlement' => [
        'bearer' => env('VOTING_PAYSTACK_BEARER', 'subaccount'),
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

    /*
     |--------------------------------------------------------------------------
     | Pending Payment Sweeper
     |--------------------------------------------------------------------------
     |
     | payments:reconcile-pending re-verifies pending payments older than
     | verify_after_minutes, and expires those still unpaid after
     | expire_after_hours. A late success on an expired payment is still credited
     | (or recorded as paid_uncredited if voting has closed).
     |
     */

    'sweeper' => [
        'verify_after_minutes' => (int) env('VOTING_SWEEPER_VERIFY_AFTER_MINUTES', 10),
        'expire_after_hours' => (int) env('VOTING_SWEEPER_EXPIRE_AFTER_HOURS', 24),
        'batch_size' => (int) env('VOTING_SWEEPER_BATCH_SIZE', 500),
    ],

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
