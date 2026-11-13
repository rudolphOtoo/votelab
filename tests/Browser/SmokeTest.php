<?php

declare(strict_types=1);

it('loads the sign-in page on a phone without JavaScript errors', function (): void {
    visit('/login')
        ->resize(360, 800)
        ->assertSee('Sign in')
        ->assertNoJavaScriptErrors()
        // D-124: sign-in loads the auth bundle (passkeys), not the dashboard's.
        ->assertSourceHas('/build/assets/auth-')
        ->assertSourceMissing('/build/assets/admin-')
        ->screenshot(filename: 'smoke-login-360');
})->group('critical');
