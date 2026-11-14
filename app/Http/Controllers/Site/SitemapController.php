<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Support\Site\SiteContext;
use App\Support\Site\SiteDirectory;
use Illuminate\Http\Response;

/**
 * sitemap.xml: the public site's pages and every listed program with its public categories. URLs and dates only:
 * never a count, an order or a winner, and never a hidden program or a draft category.
 */
final class SitemapController
{
    public function __invoke(): Response
    {
        $pages = array_map(static fn(string $route): array => ['url' => route($route), 'updated' => null], [
            'home',
            'site.results',
            'site.organizers',
            'site.help',
            'site.contact',
            'site.terms',
            'site.privacy',
            'site.refunds',
        ]);

        return response()
            ->view('site.sitemap', ['entries' => [...$pages, ...SiteDirectory::sitemap(SiteContext::scope())]])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
