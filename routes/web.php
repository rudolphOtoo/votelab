<?php

declare(strict_types=1);

use App\Http\Controllers\Payment\PaymentCallbackController;
use App\Http\Middleware\EnsureVoterCookie;
use App\Livewire\Public\CategoryShow;
use App\Livewire\Public\ProgramLanding;
use App\Support\DedicatedSite;
use Illuminate\Support\Facades\Route;

Route::get('/payments/callback', PaymentCallbackController::class)->middleware('throttle:30,1')->name(
    'payments.callback',
);

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
        });
});
