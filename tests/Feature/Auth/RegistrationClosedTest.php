<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;

it('has public registration switched off', function (): void {
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

it('serves no registration page', function (): void {
    $this->get('/register')->assertNotFound();
});

it('accepts no registration submissions and creates no user', function (): void {
    $secret = Str::password(16);

    $this->post('/register', [
        'name' => 'Walk-in',
        'email' => 'walk-in@example.com',
        'password' => $secret,
        'password_confirmation' => $secret,
    ])->assertNotFound();

    expect(User::query()->where('email', 'walk-in@example.com')->exists())->toBeFalse();
});
