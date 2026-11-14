<?php

declare(strict_types=1);

use App\Actions\Admin\SaveProgramAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;

/*
 * "Find your nominee" on the homepage (/?q=), across every listed program. A code jumps to the nominee only when
 * exactly one program has it; names and shared codes list matches by name, never by votes. Works without JavaScript.
 */

function searchableNominee(
    string $name,
    string $code,
    string $program = 'Campus Awards',
    int $votes = 0,
    CategoryStatus $status = CategoryStatus::Open,
): Nominee {
    $awardProgram = AwardProgram::query()->where('title', $program)->first() ?? AwardProgram::factory()->create([
        'title' => $program,
        'status' => ProgramStatus::Published,
    ]);
    $category = AwardCategory::factory()->for($awardProgram)->status($status)->create(['name' => "{$name}'s Category"]);

    return withLedgerVotes(Nominee::factory()->for($category)->create([
        'name' => $name,
        'stage_name' => null,
        'nominee_code' => $code,
    ]), $votes);
}

it('jumps straight to the nominee when exactly one program has the code, however it is typed', function (string $typed): void {
    $nominee = searchableNominee('Ama Mensah', 'AM27');

    $this->get('/?q=' . urlencode($typed))->assertRedirect(route('public.nominee', [
        $nominee->awardProgram->organization,
        $nominee->awardProgram,
        'AM27',
    ]));
})->with(['AM27', 'am27', ' am-27 ', 'AM 27']);

it('lists every program that has a code instead of guessing between them', function (): void {
    searchableNominee('Ama Mensah', 'AM27', 'Campus Awards');
    searchableNominee('Akosua Manu', 'AM27', 'Music Awards');

    $this
        ->get('/?q=AM27')
        ->assertOk()
        ->assertSee('Nominees matching')
        ->assertSee('Ama Mensah')
        ->assertSee('Akosua Manu')
        ->assertSee('Campus Awards')
        ->assertSee('Music Awards');
});

it('finds names whatever the case, sorted by name and never by votes', function (): void {
    searchableNominee('Zara Owusu', 'ZO11', votes: 987_654);
    searchableNominee('Abena Owusu', 'AO12', votes: 3);

    $this
        ->get('/?q=OWUSU')
        ->assertOk()
        ->assertSeeInOrder(['Abena Owusu', 'Zara Owusu'])
        ->assertDontSee('987,654')
        ->assertDontSee('987654');
});

it('never finds nominees in hidden, draft or unpublished places, or withdrawn nominees', function (): void {
    $hidden = searchableNominee('Hidden Hana', 'HH01', 'Hidden Awards');
    app(SaveProgramAction::class)->setHiddenFromHomepage(
        $hidden->awardProgram,
        organizer(OrganizationRole::Owner, $hidden->awardProgram->organization),
        true,
    );
    searchableNominee('Draft Dede', 'DD01', status: CategoryStatus::Draft);
    $unpublished = searchableNominee('Unpublished Uche', 'UU01', 'Draft Awards');
    $unpublished->awardProgram->forceFill(['status' => ProgramStatus::Draft])->save();
    searchableNominee('Withdrawn Wale', 'WW01')->forceFill(['archived_at' => now()])->save();

    foreach (['HH01', 'Hana', 'DD01', 'Dede', 'UU01', 'Uche', 'WW01', 'Wale'] as $query) {
        $this
            ->get('/?q=' . $query)
            ->assertOk()
            ->assertSee('No nominee matches');
    }
});

it('still finds nominees in categories with hidden or revealed results', function (CategoryStatus $status): void {
    searchableNominee('Kofi Boateng', 'KB30', status: $status);

    $this->get('/?q=kofi')->assertOk()->assertSee('Kofi Boateng');
})->with([CategoryStatus::Embargoed, CategoryStatus::Closed, CategoryStatus::Revealed]);

it('needs at least two characters for a name search', function (): void {
    searchableNominee('Ama Mensah', 'AM27');

    $this->get('/?q=a')->assertOk()->assertSee('No nominee matches')->assertDontSee('Ama Mensah');
});
