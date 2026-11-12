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

function something()
{
    // ..
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
