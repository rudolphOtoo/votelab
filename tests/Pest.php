<?php

declare(strict_types=1);

use App\Contracts\PaymentGateway;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

/*
 |--------------------------------------------------------------------------
 | Test Case
 |--------------------------------------------------------------------------
 |
 | The closure you provide to your test functions is always bound to a specific PHPUnit test
 | case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
 | need to change it using the "pest()" function to bind different classes or traits.
 |
 */

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(Tests\BrowserTestCase::class)->use(RefreshDatabase::class)->in('Browser');

/*
 |--------------------------------------------------------------------------
 | Expectations
 |--------------------------------------------------------------------------
 |
 | When you're writing tests, you often need to check that values meet certain conditions. The
 | "expect()" function gives you access to a set of "expectations" methods that you can use
 | to assert different things. Of course, you may extend the Expectation API at any time.
 |
 */

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
 |--------------------------------------------------------------------------
 | Functions
 |--------------------------------------------------------------------------
 |
 | While Pest is very powerful out-of-the-box, you may have some testing code specific to your
 | project that you don't want to repeat in every file. Here you can also expose helpers as
 | global functions to help you to reduce the number of lines of code in your test files.
 |
 */

/**
 * Put a category's current standings in the leaderboard cache, as a rebuild does, whatever the category's
 * status: a fixture for "counts already sit in the cache" (e.g. from before results were hidden).
 */
function cacheLeaderboardCopy(int $awardCategoryId): void
{
    Illuminate\Support\Facades\Cache::put(
        App\Support\VoteKeys::leaderboard($awardCategoryId),
        App\Support\LeaderboardSource::standings($awardCategoryId),
        600,
    );
    Illuminate\Support\Facades\Cache::put(
        App\Support\VoteKeys::leaderboardFresh($awardCategoryId),
        true,
        (int) config('voting.leaderboard_cache_seconds'),
    );
}

/**
 * Bind an in-memory payment gateway so no test ever reaches Paystack.
 */
function fakeGateway(): FakePaymentGateway
{
    $gateway = new FakePaymentGateway();
    app()->instance(PaymentGateway::class, $gateway);

    return $gateway;
}

/**
 * A user who belongs to an organization with the given role (a new organization unless one is given).
 * Owners and admins get two-factor by default, so the two-factor requirement doesn't get in the way.
 */
function organizer(OrganizationRole $role = OrganizationRole::Owner, ?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();
    $factory = User::factory();
    $user = ($role === OrganizationRole::Staff ? $factory : $factory->withTwoFactor())->create();
    $organization->addMember($user, $role);

    return $user;
}

/**
 * Give a nominee votes the way production does: ledger rows (vote_records, D-05) and the matching counter,
 * so fixtures never show drift. One free-vote row per call carries the whole quantity.
 */
function withLedgerVotes(App\Models\Nominee $nominee, int $votes): App\Models\Nominee
{
    if ($votes > 0) {
        App\Models\VoteRecord::factory()->for($nominee)->create(['quantity' => $votes]);
    }

    $nominee->forceFill(['votes_count' => $votes])->save();

    return $nominee;
}

/**
 * Mark the current session's password as just confirmed (Fortify's password.confirm).
 */
function confirmPassword(): void
{
    session()->put('auth.password_confirmed_at', time());
}

/**
 * Route the audit log channel to an in-memory handler and return it, so a test can assert entries.
 */
function captureAuditLog(): Monolog\Handler\TestHandler
{
    return captureLog('audit');
}

/**
 * Route a log channel to an in-memory handler and return it.
 */
function captureLog(string $channel): Monolog\Handler\TestHandler
{
    $handler = new Monolog\Handler\TestHandler();
    config(["logging.channels.{$channel}" => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class]]);
    Illuminate\Support\Facades\Log::channel($channel)->getLogger()->setHandlers([$handler]);

    return $handler;
}

/**
 * The password every UserFactory user has (a fixture, not a credential).
 */
function factoryPassword(): string
{
    return 'password';
}

/**
 * Send a real request to Livewire's update endpoint, as the browser does after a page load, calling one
 * method on the first component in the page HTML. Unlike Livewire::test(), this goes through the update
 * route, so Livewire re-applies the page's persistent middleware.
 *
 * @param  array<string, mixed>  $updates  Property values to set before the call.
 * @param  list<mixed>  $params
 */
function livewireCall(
    string $pageHtml,
    string $method,
    array $updates = [],
    array $params = [],
): Illuminate\Testing\TestResponse {
    preg_match('/wire:snapshot="([^"]+)"/', $pageHtml, $match);
    expect($match)->not->toBeEmpty('No Livewire component found in the page.');

    $snapshot = htmlspecialchars_decode($match[1], ENT_QUOTES);

    return test()->withHeaders(['X-Livewire' => '1'])->postJson(app('livewire')->getUpdateUri(), [
        'components' => [
            [
                'snapshot' => $snapshot,
                'updates' => $updates,
                'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
            ],
        ],
    ]);
}

/*
 |--------------------------------------------------------------------------
 | Browser tests: wait for a condition, never for a fixed time
 |--------------------------------------------------------------------------
 |
 | A fixed ->wait() is either too short on a slow CI machine (flaky) or too long everywhere else (slow).
 | These wait for what the test actually needs, check every 50 ms, and fail with the condition if it never
 | holds. The test server keeps answering requests while the browser waits.
 |
 */

/**
 * Wait until a JavaScript expression is truthy in the page. Polls in slices of at most 4 s, under
 * Playwright's 5 s limit for one evaluation, until $seconds have passed.
 */
function waitUntil(mixed $page, string $condition, float $seconds = 10.0): mixed
{
    $deadline = microtime(true) + $seconds;

    do {
        $slice = (int) (max(0.05, min(4.0, $deadline - microtime(true))) * 1000);
        $met = $page->script(<<<JS
            () => new Promise((resolve) => {
                const deadline = Date.now() + {$slice};
                const check = () => {
                    let met = false;
                    try { met = Boolean({$condition}); } catch (error) { met = false; }
                    if (met || Date.now() > deadline) { resolve(met); } else { setTimeout(check, 50); }
                };
                check();
            })
            JS);
    } while ($met !== true && microtime(true) < $deadline);

    expect($met)->toBeTrue("Gave up after {$seconds} s waiting for: {$condition}");

    return $page;
}

/**
 * Wait until the page has loaded, its fonts are ready and no finite animation or transition is running
 * (opening sheets, fades), so screenshots and accessibility checks see the final state.
 */
function waitUntilSettled(mixed $page): mixed
{
    return waitUntil(
        $page,
        "document.readyState === 'complete' && document.fonts.status === 'loaded'"
        . " && document.getAnimations().every((a) => a.playState !== 'running'"
        . ' || a.effect?.getComputedTiming().iterations === Infinity)',
    );
}

/**
 * Re-render every Livewire component on the page through a real update request (what a poll does) and wait
 * until the new HTML is in place.
 */
function refreshLivewire(mixed $page): mixed
{
    $milliseconds = 4000; // under Playwright's 5 s limit for one evaluation
    $refreshed = $page->script(<<<JS
        () => new Promise((resolve) => {
            const components = window.Livewire?.all() ?? [];
            if (components.length === 0) { resolve(false); return; }
            let pending = components.length;
            window.Livewire.hook('commit', ({ succeed, fail }) => {
                succeed(() => setTimeout(() => { if (--pending === 0) resolve(true); }, 50));
                fail(() => resolve(false));
            });
            components.forEach((component) => component.\$wire.\$refresh());
            setTimeout(() => resolve(false), {$milliseconds});
        })
        JS);

    expect($refreshed)->toBeTrue('The Livewire refresh did not complete.');

    return $page;
}

/**
 * Wait for something on the server side (e.g. a row the request should have written), letting the test
 * server keep handling the browser's requests meanwhile.
 */
function waitForServer(mixed $page, Closure $condition, string $what, float $seconds = 10.0): void
{
    $deadline = microtime(true) + $seconds;

    while (!$condition()) {
        if (microtime(true) > $deadline) {
            throw new PHPUnit\Framework\AssertionFailedError("Gave up after {$seconds} s waiting for {$what}.");
        }

        $page->wait(0.05);
    }
}
