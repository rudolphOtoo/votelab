<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Enums\TransactionStatus;
use App\Enums\VotingMode;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoteTransaction;
use App\Support\Audit;

// Advisory group (D-113): every organizer screen at 360, 768 and 1440 px for review, each with axe at every
// impact level. UI Step 3.

function organizerPortrait(int $hue): string
{
    $svg =
        "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 500'><rect width='400' height='500' fill='hsl({$hue},45%,82%)'/>"
        . "<circle cx='200' cy='190' r='86' fill='hsl({$hue},30%,42%)'/><rect x='70' y='300' width='260' height='260' rx='130' fill='hsl({$hue},30%,42%)'/></svg>";

    return 'data:image/svg+xml,' . rawurlencode($svg);
}

/**
 * @return array{0: User, 1: AwardProgram, 2: AwardCategory}
 */
function organizerShowcase(): array
{
    $organization = Organization::factory()->create([
        'name' => 'Accra Campus Creatives',
        'slug' => 'campus',
        'paystack_subaccount_code' => 'ACCT_demo123',
    ]);
    $owner = organizer(OrganizationRole::Owner, $organization);
    $owner->forceFill(['name' => 'Ama Owusu', 'email' => 'ama@campus.example'])->save();
    organizer(OrganizationRole::Staff, $organization)->forceFill(['name' => 'Kojo Mensah'])->save();
    $program = AwardProgram::factory()->for($organization)->create([
        'title' => 'Campus Excellence Awards 2026',
        'slug' => 'awards',
        'status' => ProgramStatus::Published,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->addDays(4),
    ]);

    $categories = [];
    foreach ([
        ['Artiste of the Year', CategoryStatus::Open],
        ['Best Dancer',         CategoryStatus::Embargoed],
        ['Best Newcomer',       CategoryStatus::Closed],
    ] as $i => [$name, $status]) {
        $categories[$i] = AwardCategory::factory()
            ->for($program)
            ->status($status)
            ->mode(VotingMode::Paid)
            ->create(['name' => $name, 'price_per_vote' => 100, 'display_order' => $i]);

        foreach (['Akosua Mensah-Boateng', 'Kwabena Owusu-Ansah', 'Efua Asantewaa'] as $j => $nominee) {
            $created = Nominee::factory()->for($categories[$i])->create([
                'name' => $nominee,
                'stage_name' => null,
                'avatar_url' => organizerPortrait((($i * 90) + ($j * 50)) % 360),
                'display_order' => $j,
            ]);
            withLedgerVotes($created, [1240, 3912, 860][$j]);
        }
    }

    $first = $categories[0]->nominees()->firstOrFail();
    foreach ([
        TransactionStatus::Successful,
        TransactionStatus::Successful,
        TransactionStatus::Pending,
        TransactionStatus::PaidUncredited,
    ] as $status) {
        VoteTransaction::factory()->for($first)->create([
            'status' => $status,
            'quantity' => 20,
            'credited_at' => $status === TransactionStatus::Successful ? now() : null,
        ]);
    }
    Audit::record('category.status_changed', $owner, $categories[1], [
        'before' => ['status' => 'open'],
        'after' => ['status' => 'embargoed'],
    ]);

    return [$owner, $program, $categories[0]];
}

it('renders every organizer screen accessibly at each width', function (string $screen, int $width, int $height): void {
    [$owner, $program, $category] = organizerShowcase();
    $this->actingAs($owner);

    if ($screen === 'dashboard-empty') {
        $this->actingAs(organizer(OrganizationRole::Owner, Organization::factory()->create([
            'name' => 'New Organizer Ltd',
        ])));
    }

    $path = match ($screen) {
        'dashboard', 'dashboard-empty' => '/dashboard',
        'overview' => "/dashboard/programs/{$program->id}",
        'categories', 'confirm-close' => "/dashboard/programs/{$program->id}/categories",
        'nominees', 'share-kit' => "/dashboard/programs/{$program->id}/categories/{$category->id}/nominees",
        'results',
        'transactions',
        'refunds',
        'revenue',
        'history',
            => "/dashboard/programs/{$program->id}/results?tab={$screen}",
        'members' => '/dashboard/organizations/campus/members',
        'settings' => '/dashboard/organizations/campus/members?tab=settings',
    };

    $page = waitUntilSettled(visit($path)->resize($width, $height));

    if ($screen === 'confirm-close') {
        waitUntil(
            $page->click('Close voting'),
            '[...document.querySelectorAll("[role=dialog]")].some((dialog) => dialog.checkVisibility())',
        );
    }

    if ($screen === 'share-kit') {
        waitUntil(
            $page->click('Share kit'),
            'document.getElementById(document.querySelector("[aria-controls^=share-][aria-expanded=true]")'
            . '?.getAttribute("aria-controls"))?.checkVisibility()',
        );
    }

    waitUntilSettled($page);

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(3)
        ->screenshot(fullPage: $screen !== 'confirm-close', filename: "organizer-{$screen}-{$width}");
})
    ->with([
        'dashboard-empty',
        'dashboard',
        'overview',
        'categories',
        'confirm-close',
        'nominees',
        'share-kit',
        'results',
        'transactions',
        'refunds',
        'revenue',
        'history',
        'members',
        'settings',
    ])
    ->with([
        'phone' => [360, 800],
        'tablet' => [768, 1024],
        'desktop' => [1440, 900],
    ])
    ->group('advisory', 'screenshots');

it('renders the sign-in pages accessibly at each width', function (string $screen, int $width, int $height): void {
    $path = match ($screen) {
        'sign-in' => '/login',
        'forgot-password' => '/forgot-password',
    };

    visit($path)
        ->resize($width, $height)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues(3)
        ->screenshot(filename: "auth-{$screen}-{$width}");
})
    ->with(['sign-in', 'forgot-password'])
    ->with([
        'phone' => [360, 800],
        'tablet' => [768, 1024],
        'desktop' => [1440, 900],
    ])
    ->group('advisory', 'screenshots');
