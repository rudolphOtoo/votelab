<?php

declare(strict_types=1);

it('shows the component gallery outside production', function (): void {
    $this
        ->get('/dev/components')
        ->assertOk()
        ->assertSee('VoteLab design system')
        ->assertSee('Contrast (WCAG 2.2 AA)')
        ->assertSee('Voting open · results hidden')
        ->assertDontSee('FAIL');
});

it('does not exist in production', function (): void {
    app()->detectEnvironment(static fn(): string => 'production');

    $this->get('/dev/components')->assertNotFound();
});

it('reserves the dev slug so no organization can take the gallery path', function (): void {
    expect(App\Rules\OrganizationSlug::isReserved('dev'))->toBeTrue();
});
