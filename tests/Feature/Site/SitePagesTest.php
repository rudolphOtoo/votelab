<?php

declare(strict_types=1);

use App\Enums\ProgramStatus;
use App\Models\AwardProgram;

/*
 * The public site's pages (plan/SITE.md): content from config/site.php (dummy until replaced), the legal draft notice,
 * robots.txt, and which pages search engines may index.
 */

it('serves every site page, indexable, with its canonical link', function (string $route, string $text): void {
    $this
        ->get(route($route))
        ->assertOk()
        ->assertSeeText($text)
        ->assertSee('<link rel="canonical" href="' . route($route) . '">', escape: false)
        ->assertDontSee('name="robots"', escape: false);
})->with([
    ['home',                'Vote for your favourites.'],
    ['site.results',        'Winners announced'],
    ['site.organizers',     'Run your awards on VoteLab.'],
    ['site.request-access', 'Request access'],
    ['site.help',           'How do I vote?'],
    ['site.contact',        'Talk to us'],
    ['site.terms',          'Terms of use'],
    ['site.privacy',        'Privacy notice'],
    ['site.refunds',        'Refund policy'],
]);

it('shows the support contacts and fee wording from config/site.php', function (): void {
    config([
        'site.support' => [
            'email' => 'help@votelab.test',
            'whatsapp' => '+233 20 111 2222',
            'phone' => '+233 30 333 4444',
        ],
        'site.fee' => 'A test fee wording.',
    ]);

    $this
        ->get(route('site.contact'))
        ->assertSee('help@votelab.test')
        ->assertSee('https://wa.me/233201112222')
        ->assertSee('tel:+233303334444');
    $this->get(route('site.organizers'))->assertSee('A test fee wording.');
    $this->get(route('home'))->assertSee('A test fee wording.')->assertSee('help@votelab.test');
});

it('marks the legal pages as drafts until the flag is turned off', function (string $route): void {
    $this->get(route($route))->assertSee('Draft — pending legal review.');

    config(['site.legal.draft' => false]);
    $this->get(route($route))->assertDontSee('Draft — pending legal review.');
})->with(['site.terms', 'site.privacy', 'site.refunds']);

it('states the real retention periods and the votes-are-final rule', function (): void {
    $this
        ->get(route('site.privacy'))
        ->assertSeeText('deleted 180 days after you send them')
        ->assertSeeText('payments ' . config('logging.channels.payments.max_files') . ' days')
        ->assertSeeText('Data Protection Act, 2012 (Act 843)');
    $this
        ->get(route('site.refunds'))
        ->assertSeeText('Votes are final once counted')
        ->assertSeeText('after the winner was revealed');
});

it('tells crawlers to stay out of private areas and where the sitemap is', function (): void {
    $robots = $this
        ->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->getContent();

    expect($robots)
        ->toContain("Disallow: /dashboard\n")
        ->toContain("Disallow: /stage\n")
        ->toContain("Disallow: /payments\n")
        ->toContain("Disallow: /dev\n")
        ->toContain('Sitemap: ' . route('site.sitemap'))
        ->and(file_exists(public_path('robots.txt')))
        ->toBeFalse(); // a static file would be served instead of this route
});

it('keeps the stage, sign-in and invitation pages out of search results', function (): void {
    $program = AwardProgram::factory()->create(['status' => ProgramStatus::Published]);

    $this->get(route('stage', [$program->organization, $program]))->assertSee(
        '<meta name="robots" content="noindex">',
        escape: false,
    );
    $this->get(route('login'))->assertSee('<meta name="robots" content="noindex">', escape: false);
    $this->get(route('password.request'))->assertSee('<meta name="robots" content="noindex">', escape: false);
});

it('no longer ships Laravel\'s welcome page', function (): void {
    expect(view()->exists('welcome'))->toBeFalse();
});
