<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Support\Site\SiteContext;
use App\Support\Site\SiteDirectory;
use Illuminate\Contracts\View\View;

/**
 * The public site's pages around the homepage (plan/SITE.md). In dedicated mode they exist too, scoped to that
 * organization.
 */
final class SitePageController
{
    /**
     * Revealed categories and their winners, grouped by program. Never a hidden or unrevealed category.
     */
    public function results(): View
    {
        $scope = SiteContext::scope();
        $programs = [];

        foreach (SiteDirectory::winners($scope, 120) as $category) {
            $programs[$category['programUrl']] ??= [
                'title' => $category['program'],
                'url' => $category['programUrl'],
                'organization' => $category['organization'],
                'categories' => [],
            ];
            $programs[$category['programUrl']]['categories'][] = $category;
        }

        return view('site.results', ['scope' => $scope, 'programs' => array_values($programs)]);
    }

    public function organizers(): View
    {
        return view('site.organizers', SiteContext::shared());
    }

    public function help(): View
    {
        return view('site.help', SiteContext::shared());
    }

    public function contact(): View
    {
        return view('site.contact', SiteContext::shared());
    }

    public function legal(string $page): View
    {
        return match ($page) {
            'terms' => view('site.legal.terms', SiteContext::shared()),
            'privacy' => view('site.legal.privacy', SiteContext::shared()),
            'refunds' => view('site.legal.refunds', SiteContext::shared()),
            default => abort(404),
        };
    }
}
