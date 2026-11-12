<?php

declare(strict_types=1);

it('loads the sign-in page on a phone without JavaScript errors', function (): void {
    visit('/login')
        ->resize(360, 800)
        ->assertSee('Sign in')
        ->assertNoJavaScriptErrors()
        ->screenshot(filename: 'smoke-login-360');
})->group('critical');
