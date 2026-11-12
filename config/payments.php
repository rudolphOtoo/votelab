<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | PayZephyr overrides
 |--------------------------------------------------------------------------
 |
 | PayZephyr's own config (vendor/kendenigerian/payzephyr/config/payments.php) is merged under this
 | file: each top-level key here replaces the package's key of the same name, every other key keeps
 | the package default.
 |
 | Transaction logging: PayZephyr defaults it to on, which reads and writes its own
 | `payment_transactions` table on every verification. VoteLab doesn't use that table
 | (the database tables `vote_transactions` and `vote_records` are the ledger, D-05) and doesn't run PayZephyr's migrations, so with
 | logging on, a payment-result page could fail with a missing-table error (D-122). It is off
 | unless PAYMENTS_LOGGING_ENABLED=true is set deliberately, and production refuses to boot with
 | it on (ProductionGuard).
 |
 | Fallback provider and currency: PayZephyr defaults to a Stripe fallback and NGN. They only affect
 | charges made through PayZephyr, which VoteLab doesn't make (checkout calls Paystack directly, D-38),
 | but the safe values are set here so a missing variable can never select them.
 |
 */

return [
    // Empty (as in .env.example) or unset: no fallback.
    'fallback' => in_array(env('PAYMENTS_FALLBACK_PROVIDER'), [null, ''], true)
        ? null
        : env('PAYMENTS_FALLBACK_PROVIDER'),

    'currency' => [
        'default' => env('PAYMENTS_DEFAULT_CURRENCY', 'GHS'),
    ],

    'logging' => [
        'enabled' => (bool) env('PAYMENTS_LOGGING_ENABLED', false),
        'table' => 'payment_transactions',
        'channel' => env('PAYMENTS_LOG_CHANNEL', 'payments'),
    ],
];
