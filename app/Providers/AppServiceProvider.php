<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payment\PaystackFeeCalculator;
use App\Services\Payment\PaystackPaymentGateway;
use App\Services\Security\VoteFingerprintService;
use App\Services\Security\VoterIdentity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

        RateLimiter::for('vote-checkout', static fn(VoterIdentity $voter, int $nomineeId): array => [
            Limit::perMinute($limits('checkout_per_voter'))->by("voter:{$voter->voterKey}"),
            Limit::perMinute($limits('checkout_per_ip'))->by("ip:{$voter->ipHash}"),
            Limit::perMinute($limits('checkout_per_nominee'))->by("nominee:{$nomineeId}"),
        ]);

        RateLimiter::for('free-vote', static fn(VoterIdentity $voter): array => [
            Limit::perMinute($limits('free_vote_per_voter'))->by("voter:{$voter->voterKey}"),
            Limit::perMinute($limits('free_vote_per_ip'))->by("ip:{$voter->ipHash}"),
        ]);
    }
}
