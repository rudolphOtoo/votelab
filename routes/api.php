<?php

declare(strict_types=1);

use App\Http\Controllers\Payment\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

// Stateless, CSRF-free (api middleware group). Point the Paystack dashboard webhook URL here.
Route::post('/webhooks/paystack', PaystackWebhookController::class)
    ->middleware('throttle:240,1')
    ->name('webhooks.paystack');
