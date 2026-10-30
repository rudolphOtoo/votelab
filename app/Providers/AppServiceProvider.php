<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Payment\PaystackFeeCalculator;
use App\Services\Payment\PaystackPaymentGateway;
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
    public function boot(): void {}
}
