<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use Illuminate\Http\Response;

/**
 * robots.txt: public pages may be crawled; the dashboard, stage, payment, invitation and dev pages may not (they are
 * also noindex). Points crawlers at sitemap.xml.
 */
final class RobotsController
{
    public const array DISALLOW = ['/dashboard', '/stage', '/payments', '/invitations', '/dev', '/livewire'];

    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            ...array_map(static fn(string $path): string => "Disallow: {$path}", self::DISALLOW),
        ];
        $lines[] = '';
        $lines[] = 'Sitemap: ' . route('site.sitemap');

        return response(implode("\n", $lines) . "\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
