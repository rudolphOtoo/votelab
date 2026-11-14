<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Models\Organization;
use App\Support\DedicatedSite;

/**
 * Who the public site is for: VoteLab as a whole, or in dedicated mode one organization (plan/SITE.md decision 6),
 * whose programs alone are listed, searched and shown in /results and the sitemap.
 */
final class SiteContext
{
    public static function scope(): ?Organization
    {
        return DedicatedSite::resolve()?->organization;
    }

    /**
     * View data every site page shares.
     *
     * @return array{scope: ?Organization}
     */
    public static function shared(): array
    {
        return ['scope' => self::scope()];
    }
}
