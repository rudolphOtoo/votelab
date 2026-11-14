<?php

declare(strict_types=1);

use App\Support\Site\DummySiteData;

/*
 |--------------------------------------------------------------------------
 | Public site: homepage, results, organizer and legal pages (plan/SITE.md)
 |--------------------------------------------------------------------------
 |
 | DUMMY VALUES (owner, 2026-11-14: "dummy data for now"; App\Support\Site\DummySiteData). Every contact and
 | business detail below is a stand-in that can't be mistaken for a real one: an address on .invalid, which can never
 | receive mail, numbers of zeros, a bracketed name. Production refuses to start until every one is replaced through
 | the environment (D-149; README production checklist). The legal pages also carry a visible "Draft — pending legal
 | review" notice while legal_draft is true.
 |
 */

return [
    // Shown to organizers on the homepage and /organizers.
    'fee' => env('SITE_FEE_WORDING', DummySiteData::FEE),

    'support' => [
        // Request-access notifications are emailed here (queued).
        'email' => env('SITE_SUPPORT_EMAIL', DummySiteData::SUPPORT_EMAIL),
        'whatsapp' => env('SITE_SUPPORT_WHATSAPP', DummySiteData::SUPPORT_WHATSAPP),
        'phone' => env('SITE_SUPPORT_PHONE', DummySiteData::SUPPORT_PHONE),
    ],

    'legal' => [
        'name' => env('SITE_LEGAL_NAME', DummySiteData::LEGAL_NAME),
        'address' => env('SITE_LEGAL_ADDRESS', DummySiteData::LEGAL_ADDRESS),
        'country' => 'Ghana',
        // The "Draft — pending legal review" notice on /terms, /privacy and /refunds. Set false once a lawyer approves.
        'draft' => (bool) env('SITE_LEGAL_DRAFT', true),
    ],

    // Request-access submissions are deleted this many days after they arrive (plan/SITE.md decision 8).
    'access_request_retention_days' => 180,

    // Homepage and /results lists are cached this long, and cleared whenever a program or category changes.
    'listing_cache_seconds' => 60,
];
