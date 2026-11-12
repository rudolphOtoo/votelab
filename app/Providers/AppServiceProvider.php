<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Http\Middleware\RequireTwoFactorForManagers;
use App\Models\Organization;
use App\Models\User;
use App\Policies\ProgramReportingPolicy;
use App\Services\Payment\PaystackFeeCalculator;
use App\Services\Payment\PaystackPaymentGateway;
use App\Services\Security\PhoneHasher;
use App\Services\Security\VoteFingerprintService;
use App\Services\Security\VoterIdentity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, PaystackPaymentGateway::class);
        $this->app->bind(PaystackFeeCalculator::class, PaystackFeeCalculator::fromConfig(...));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(!$this->app->isProduction());

        // Production must not start without its dedicated phone hash key (D-101).
        if ($this->app->isProduction()) {
            PhoneHasher::ensureConfigured();
        }

        // Organizer passwords: 12+ characters; in production also refused if found in known breaches
        // (Have I Been Pwned range API, k-anonymity: only a 5-character hash prefix leaves the server).
        Password::defaults(fn(): Password => $this->app->isProduction()
            ? Password::min(12)->uncompromised()
            : Password::min(12));

        // Program reporting abilities (results, money, ledger reading) live in their own policy class.
        foreach (ProgramReportingPolicy::ABILITIES as $ability) {
            Gate::define($ability, [ProgramReportingPolicy::class, $ability]);
        }

        // Re-applied to Livewire update requests from dashboard pages, so actions are blocked too.
        Livewire::addPersistentMiddleware([RequireTwoFactorForManagers::class]);

        $this->configureRateLimiting();
    }

    /**
     * Named limiters. Route middleware uses `public-pages`; Livewire actions apply the others through
     * App\Support\Throttle with the voter's hashed identity, since they do not pass through route middleware.
     */
    private function configureRateLimiting(): void
    {
        $limits = static fn(string $name): int => (int) config("voting.rate_limits.{$name}");

        RateLimiter::for('public-pages', static fn(Request $request): Limit => Limit::perMinute($limits(
            'public_pages_per_ip',
        ))->by(app(VoteFingerprintService::class)->identify($request)->ipHash));

        RateLimiter::for('share-images', static fn(Request $request): Limit => Limit::perMinute($limits(
            'share_images_per_ip',
        ))->by(app(VoteFingerprintService::class)->identify($request)->ipHash));

        // Stage screen: page loads (route middleware, given the request) and polls (Throttle, given the IP hash).
        RateLimiter::for('stage', static fn(Request|string $from): Limit => Limit::perMinute($limits(
            'stage_per_ip',
        ))->by('stage:' . (is_string($from) ? $from : app(VoteFingerprintService::class)->identify($from)->ipHash)));

        RateLimiter::for('vote-checkout', static fn(VoterIdentity $voter, int $nomineeId): array => [
            Limit::perMinute($limits('checkout_per_voter'))->by("voter:{$voter->voterKey}"),
            Limit::perMinute($limits('checkout_per_ip'))->by("ip:{$voter->ipHash}"),
            Limit::perMinute($limits('checkout_per_nominee'))->by("nominee:{$nomineeId}"),
        ]);

        RateLimiter::for('organization-invitations', static fn(User $inviter, Organization $organization): array => [
            Limit::perHour((int) config('organizations.invitation_rate_limits.per_inviter_per_hour'))->by(
                "inviter:{$inviter->id}",
            ),
            Limit::perDay((int) config('organizations.invitation_rate_limits.per_organization_per_day'))->by(
                "organization:{$organization->id}",
            ),
        ]);

        RateLimiter::for('free-vote', static fn(VoterIdentity $voter): array => [
            Limit::perMinute($limits('free_vote_per_voter'))->by("voter:{$voter->voterKey}"),
            Limit::perMinute($limits('free_vote_per_ip'))->by("ip:{$voter->ipHash}"),
        ]);
    }
}
