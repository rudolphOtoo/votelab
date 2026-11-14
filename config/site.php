<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Public site: homepage, results, organizer and legal pages (plan/SITE.md)
 |--------------------------------------------------------------------------
 |
 | DUMMY VALUES (owner, 2026-11-14: "dummy data for now"). Every contact and business detail below is a stand-in
 | that can't be mistaken for a real one: an .example address, numbers of zeros, a bracketed name. Replace them
 | through the environment before launch (README production checklist). The legal pages also carry a visible
 | "Draft — pending legal review" notice while legal_draft is true.
 |
 */

return [
    // Shown to organizers on the homepage and /organizers.
    'fee' => env(
        'SITE_FEE_WORDING',
        'Pricing is a simple share of each paid vote. Request access and we\'ll send our rates.',
    ),

    'support' => [
        // Request-access notifications are emailed here (queued).
        'email' => env('SITE_SUPPORT_EMAIL', 'support@votelab.example'),
        'whatsapp' => env('SITE_SUPPORT_WHATSAPP', '+233 20 000 0000'),
        'phone' => env('SITE_SUPPORT_PHONE', '+233 30 000 0000'),
    ],

    'legal' => [
        'name' => env('SITE_LEGAL_NAME', '[VoteLab legal name]'),
        'address' => env('SITE_LEGAL_ADDRESS', '[Registered address], Accra'),
        'country' => 'Ghana',
        // The "Draft — pending legal review" notice on /terms, /privacy and /refunds. Set false once a lawyer approves.
        'draft' => (bool) env('SITE_LEGAL_DRAFT', true),
    ],

    // Request-access submissions are deleted this many days after they arrive (plan/SITE.md decision 8).
    'access_request_retention_days' => 180,

    // Homepage and /results lists are cached this long, and cleared whenever a program or category changes.
    'listing_cache_seconds' => 60,
];
