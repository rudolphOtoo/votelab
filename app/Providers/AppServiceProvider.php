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
use App\Services\Security\VoteFingerprintService;
use App\Services\Security\VoterIdentity;
use App\Support\Observability\Alert;
use App\Support\ProductionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
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

        // Production refuses to start on an unsafe configuration (D-106, includes the phone key of D-101).
        if ($this->app->isProduction()) {
            ProductionGuard::enforce();
        }

        // Every failed job is an operator alert (D-108). Names only: payloads can hold emails.
        Queue::failing(static function (JobFailed $event): void {
            Alert::raise('queue.job_failed', [
                'job' => $event->job->resolveName(),
                'queue' => $event->job->getQueue(),
                'connection' => $event->connectionName,
                'exception' => $event->exception::class,
            ]);
        });

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
     * Named limiters (D-105). Voter-facing limits key on the device cookie with a high per-network backstop
     * (carrier-grade NAT, venue Wi-Fi); sign-in and abuse limits stay per IP. Route middleware uses
     * `public-pages`, `payment-callback`, `share-images`, `stage` and `paystack-webhook`; Livewire actions
     * apply the others through App\Support\Throttle, since they do not pass through route middleware.
     */
    private function configureRateLimiting(): void
    {
        $limits = static fn(string $name): int => (int) config("voting.rate_limits.{$name}");

        RateLimiter::for('public-pages', static function (Request $request) use ($limits): array {
            $voter = app(VoteFingerprintService::class)->identify($request);

            return [
                Limit::perMinute($limits('public_pages_per_device'))->by($voter->rateKey()),
                Limit::perMinute($limits('public_pages_per_ip'))->by("ip:{$voter->ipHash}"),
            ];
        });

        RateLimiter::for('payment-callback', static function (Request $request) use ($limits): array {
            $voter = app(VoteFingerprintService::class)->identify($request);

            return [
                Limit::perMinute($limits('callback_per_device'))->by("callback:{$voter->rateKey()}"),
                Limit::perMinute($limits('callback_per_ip'))->by("callback-ip:{$voter->ipHash}"),
            ];
        });

        RateLimiter::for('health', static fn(Request $request): Limit => Limit::perMinute(60)->by(
            (string) $request->ip(),
        ));

        RateLimiter::for('paystack-webhook', static fn(Request $request): Limit => Limit::perMinute($limits(
            'webhook_per_ip',
        ))->by((string) $request->ip()));

        RateLimiter::for('share-images', static fn(Request $request): Limit => Limit::perMinute($limits(
            'share_images_per_ip',
        ))->by(app(VoteFingerprintService::class)->identify($request)->ipHash));

        // Stage screen: page loads (route middleware, given the request) and polls (Throttle, given the IP hash).
        RateLimiter::for('stage', static fn(Request|string $from): Limit => Limit::perMinute($limits(
            'stage_per_ip',
        ))->by('stage:' . (is_string($from) ? $from : app(VoteFingerprintService::class)->identify($from)->ipHash)));

        RateLimiter::for('vote-checkout', static fn(VoterIdentity $voter, int $nomineeId): array => [
            Limit::perMinute($limits('checkout_per_voter'))->by($voter->rateKey()),
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
            Limit::perMinute($limits('free_vote_per_voter'))->by($voter->rateKey()),
            Limit::perMinute($limits('free_vote_per_ip'))->by("ip:{$voter->ipHash}"),
        ]);
    }
}
