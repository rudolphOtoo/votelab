<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ExportResultsController;
use App\Http\Controllers\Admin\NomineeOriginalPhotoController;
use App\Http\Controllers\Payment\PaymentCallbackController;
use App\Http\Controllers\Public\ShareImageController;
use App\Http\Middleware\EnsureVoterCookie;
use App\Http\Middleware\RequireTwoFactorForManagers;
use App\Livewire\Admin\AccountSecurity;
use App\Livewire\Admin\CategoryManager;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\NomineeManager;
use App\Livewire\Admin\OrganizationMembers;
use App\Livewire\Admin\ProgramCreate;
use App\Livewire\Admin\ProgramManager;
use App\Livewire\Admin\ResultsAuditor;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Public\CategoryShow;
use App\Livewire\Public\ProgramLanding;
use App\Support\DedicatedSite;
use Illuminate\Support\Facades\Route;

Route::get('/payments/callback', PaymentCallbackController::class)->middleware('throttle:30,1')->name(
    'payments.callback',
);

/*
 |--------------------------------------------------------------------------
 | Organizers
 |--------------------------------------------------------------------------
 |
 | Sign-in pages are Fortify's (config/fortify.php). The security page stays reachable when the
 | two-factor requirement locks the rest of the dashboard. Every path's first segment is a reserved
 | organization slug (config/organizations.php), so it can never be shadowed by a public page.
 |
 */

Route::livewire('/invitations/{invitation}/accept', AcceptInvitation::class)
    ->middleware(['signed', 'throttle:30,1'])
    ->whereNumber('invitation')
    ->name('invitations.accept');

Route::middleware('auth')->group(static function (): void {
    Route::livewire('/dashboard/security', AccountSecurity::class)
        ->middleware('password.confirm')
        ->name('dashboard.security');

    Route::middleware(RequireTwoFactorForManagers::class)->group(static function (): void {
        Route::livewire('/dashboard', Dashboard::class)->name('dashboard');
        Route::livewire('/dashboard/organizations/{organization:slug}/programs/new', ProgramCreate::class)->name(
            'dashboard.programs.create',
        );
        Route::livewire('/dashboard/programs/{program}', ProgramManager::class)
            ->whereNumber('program')
            ->name('dashboard.programs.show');
        Route::livewire('/dashboard/programs/{program}/categories', CategoryManager::class)
            ->whereNumber('program')
            ->name('dashboard.programs.categories');
        Route::livewire('/dashboard/programs/{program}/categories/{category}/nominees', NomineeManager::class)
            ->whereNumber(['program', 'category'])
            ->name('dashboard.programs.nominees');
        Route::livewire('/dashboard/programs/{program}/results', ResultsAuditor::class)
            ->whereNumber('program')
            ->name('dashboard.programs.results');
        Route::get('/dashboard/programs/{program}/export', ExportResultsController::class)
            ->whereNumber('program')
            ->middleware('throttle:10,1')
            ->name('dashboard.programs.export');
        Route::get('/dashboard/nominees/{nominee}/original', NomineeOriginalPhotoController::class)->whereNumber(
            'nominee',
        )->name('dashboard.nominees.original');
        Route::livewire('/dashboard/organizations/{organization:slug}/members', OrganizationMembers::class)->name(
            'dashboard.members',
        );
    });
});

/*
 |--------------------------------------------------------------------------
 | Public voting portal
 |--------------------------------------------------------------------------
 |
 | Registered last: these patterns are broad, so every fixed path (above, and package routes such as
 | Livewire's and Fortify's) must match first. scopeBindings() resolves each segment through its parent,
 | so a program, category or nominee from another organization or program is a 404.
 |
 */

Route::middleware([EnsureVoterCookie::class, 'throttle:public-pages'])->group(static function (): void {
    // Dedicated mode serves the home program at "/"; otherwise the default welcome page.
    Route::get('/', static function () {
        $site = DedicatedSite::resolve();

        if ($site?->program === null) {
            return view('welcome');
        }

        return view('public.home', ['organization' => $site->organization, 'program' => $site->program]);
    })->name('home');

    Route::scopeBindings()
        ->where([
            'organization' => '[a-z0-9]+(?:-[a-z0-9]+)*',
            'awardProgram' => '[a-z0-9]+(?:-[a-z0-9]+)*',
            'category' => '[a-z0-9]+(?:-[a-z0-9]+)*',
            'nominee' => '[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*',
        ])
        ->group(static function (): void {
            Route::livewire('/{organization:slug}/{awardProgram:slug}', ProgramLanding::class)->name('public.program');
            Route::livewire(
                '/{organization:slug}/{awardProgram:slug}/categories/{category:slug}',
                CategoryShow::class,
            )->name('public.category');
            Route::livewire(
                '/{organization:slug}/{awardProgram:slug}/{nominee:nominee_code}',
                CategoryShow::class,
            )->name('public.nominee');
            Route::get(
                '/{organization:slug}/{awardProgram:slug}/{nominee:nominee_code}/share/{format}',
                ShareImageController::class,
            )->whereIn('format', ['og', 'square', 'story'])->name('public.nominee.share');
        });
});
