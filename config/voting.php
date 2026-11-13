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
     | A payment started before a category closes is still credited if it is
     | confirmed before the reveal AND within the program's grace window of the
     | close (D-85, D-94). This is the platform maximum for that window. Otherwise it is recorded as "paid_uncredited" for refund handling.
     | RevealWinnerAction refuses while such payments are still pending, unless
     | explicitly overridden.
     |
     */

    'close_grace_minutes' => (int) env('VOTING_CLOSE_GRACE_MINUTES', 30),

    // Each program sets its own window (D-94) between this minimum and close_grace_minutes, the platform
    // maximum. New programs start at the maximum.
    'close_grace_minimum_minutes' => 10,

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
     | Voter Fingerprints
     |--------------------------------------------------------------------------
     |
     | Free-vote limits key on HMAC-SHA256(ip | user agent | device cookie) and
     | the ledger stores HMAC-SHA256(ip). Raw IPs and cookies are never stored.
     | VOTING_FINGERPRINT_KEY is required in production (the app refuses to boot
     | without it, D-126) and never falls back to APP_KEY; outside production a
     | fixed test key is used. Rotating it resets every free-vote allowance.
     |
     */

    'fingerprint_key' => env('VOTING_FINGERPRINT_KEY'),

    // Keys phone_hash on vote_transactions (exact search by phone without storing the number, D-86). Required
    // in production (the app refuses to boot without it, D-101); never falls back to APP_KEY. Rotating it makes
    // phone search miss every row hashed with the old key.
    'phone_hash_key' => env('VOTING_PHONE_HASH_KEY'),

    'voter_cookie' => env('VOTING_VOTER_COOKIE', 'vl_voter'),

    /*
     |--------------------------------------------------------------------------
     | Rate Limits (per minute)
     |--------------------------------------------------------------------------
     |
     | Checked in AppServiceProvider's named limiters. Per-IP limits are loose on
     | purpose: campus networks put many voters behind one address.
     |
     */

    // Per minute (D-105). Voter-facing limits are keyed mainly on the device cookie (EnsureVoterCookie), with
    // a much higher per-network ceiling as a backstop: Ghanaian mobile networks use carrier-grade NAT and
    // venue Wi-Fi puts hundreds of voters on one IP.
    'rate_limits' => [
        'checkout_per_voter' => (int) env('VOTING_RATE_CHECKOUT_PER_VOTER', 5), // per device
        'checkout_per_ip' => (int) env('VOTING_RATE_CHECKOUT_PER_IP', 600),
        'checkout_per_nominee' => (int) env('VOTING_RATE_CHECKOUT_PER_NOMINEE', 1200),
        'free_vote_per_voter' => (int) env('VOTING_RATE_FREE_VOTE_PER_VOTER', 10), // per device
        'free_vote_per_ip' => (int) env('VOTING_RATE_FREE_VOTE_PER_IP', 1200),
        'public_pages_per_device' => (int) env('VOTING_RATE_PUBLIC_PAGES_PER_DEVICE', 120),
        'public_pages_per_ip' => (int) env('VOTING_RATE_PUBLIC_PAGES_PER_IP', 3000),
        'callback_per_device' => (int) env('VOTING_RATE_CALLBACK_PER_DEVICE', 30),
        'callback_per_ip' => (int) env('VOTING_RATE_CALLBACK_PER_IP', 3000),
        // Share images are cached after the first render; link-preview fetchers share a few IPs.
        'share_images_per_ip' => (int) env('VOTING_RATE_SHARE_IMAGES_PER_IP', 600),
        // The stage screen polls every 5 s (12/min per screen); a few screens per venue.
        'stage_per_ip' => (int) env('VOTING_RATE_STAGE_PER_IP', 120),
        // Paystack delivers webhooks from a few IPs; signatures are checked before any work.
        'webhook_per_ip' => (int) env('VOTING_RATE_WEBHOOK_PER_IP', 1200),
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
