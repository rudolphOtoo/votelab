<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Invitations
     |--------------------------------------------------------------------------
     |
     | Organizers join only by invitation. Links are signed, single-use and
     | expire after this many hours. Sending is rate limited per inviter and
     | per organization.
     |
     */

    'invitation_ttl_hours' => (int) env('ORGANIZER_INVITATION_TTL_HOURS', 72),

    'invitation_rate_limits' => [
        'per_inviter_per_hour' => 20,
        'per_organization_per_day' => 100,
    ],

    /*
     |--------------------------------------------------------------------------
     | Two-factor requirement
     |--------------------------------------------------------------------------
     |
     | Owners and admins must turn on two-factor authentication within this
     | many days of their first sign-in. After that the dashboard is blocked
     | (only the security page stays open) until they do.
     |
     */

    'two_factor_grace_days' => (int) env('ORGANIZER_TWO_FACTOR_GRACE_DAYS', 7),

    /*
     |--------------------------------------------------------------------------
     | Nominee photos
     |--------------------------------------------------------------------------
     |
     | Uploads are checked by content (JPEG, PNG or WebP). The original is kept
     | on a private disk; the public portrait is the organizer's 4:5 crop of
     | it, re-encoded as WebP at this size.
     |
     */

    'nominee_photos' => [
        'disk' => env('NOMINEE_PHOTO_DISK', 'public'),
        // Uploaded originals, kept so a portrait can be re-cropped; never public.
        'original_disk' => env('NOMINEE_PHOTO_ORIGINAL_DISK', 'local'),
        'max_kilobytes' => 5_120,
        'width' => 800,
        'height' => 1_000,
        // Smaller copies for srcset (D-127), same 4:5 crop: cards on phones use 240 or 400.
        'variant_widths' => [400, 240],
    ],

    /*
     |--------------------------------------------------------------------------
     | Reserved slugs
     |--------------------------------------------------------------------------
     |
     | Organization slugs form the first URL segment of public pages
     | (/{organization}/{program}), so none may equal a fixed path. This is
     | the single list used by every place that creates or renames an
     | organization. A test fails if a registered route's first segment is
     | missing from it.
     |
     */

    'reserved_slugs' => [
        'admin',
        'api',
        'app',
        'assets',
        'auth',
        'build',
        'confirm-password',
        'css',
        'dashboard',
        'dev',
        'email',
        'favicon',
        'forgot-password',
        'health',
        'help',
        'home',
        'img',
        'invitations',
        'js',
        'livewire',
        'login',
        'logout',
        'mail',
        'organizations',
        'passkeys',
        'password',
        'payments',
        'register',
        'reset-password',
        'robots',
        'security',
        'settings',
        'stage',
        'static',
        'storage',
        'support',
        'two-factor-challenge',
        'up',
        'user',
        'vendor',
        'votelab',
        'webhooks',
        'www',
    ],
];
