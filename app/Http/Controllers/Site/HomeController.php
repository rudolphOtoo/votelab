<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Actions\Site\FindNomineeAcrossPrograms;
use App\Support\DedicatedSite;
use App\Support\Site\SiteDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "/" (plan/SITE.md). Dedicated mode is unchanged: the organization's program, or, when it has no published program,
 * a branded "No live program right now" page (never Laravel's welcome page). Otherwise the VoteLab homepage, whose
 * "Find your nominee" form submits ?q= here and works without JavaScript.
 */
final class HomeController
{
    public function __invoke(Request $request, FindNomineeAcrossPrograms $find): View|RedirectResponse
    {
        $site = DedicatedSite::resolve();

        if ($site !== null) {
            return (
                $site->program !== null
                    ? view('public.home', ['organization' => $site->organization, 'program' => $site->program])
                    : view('site.no-live-program', [
                        'organization' => $site->organization,
                        'scope' => $site->organization,
                    ])
            );
        }

        $query = trim($request->string('q')->toString());
        $search = $query === '' ? null : $find->execute($query);

        if ($search !== null && $search['redirect'] !== null) {
            return redirect()->to($search['redirect']);
        }

        return view('site.home', [
            'scope' => null,
            'query' => $query,
            'matches' => $search['matches'] ?? null,
            'live' => SiteDirectory::live(),
            'winners' => SiteDirectory::winners(),
        ]);
    }
}
