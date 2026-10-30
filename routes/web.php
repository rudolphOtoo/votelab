<?php

declare(strict_types=1);

use App\Http\Controllers\Payment\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/', static fn() => view('welcome'));

Route::get('/payments/callback', PaymentCallbackController::class)->middleware('throttle:30,1')->name(
    'payments.callback',
);
