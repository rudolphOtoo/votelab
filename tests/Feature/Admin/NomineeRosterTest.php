<?php

declare(strict_types=1);

use App\Actions\Admin\ArchiveNomineeAction;
use App\Actions\Admin\CreateNomineeAction;
use App\Actions\Voting\CastFreeVoteAction;
use App\Actions\Voting\QuotePaidVotesAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\VotingMode;
use App\Exceptions\Voting\CheckoutUnavailableException;
use App\Exceptions\Voting\FreeVoteUnavailableException;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Services\Security\VoterIdentity;
use App\Support\NomineeCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('public');
});

function rosterCategory(CategoryStatus $status = CategoryStatus::Draft): AwardCategory
{
    $organization = Organization::factory()->create(['slug' => 'org']);
    $program = AwardProgram::factory()->for($organization)->create(['slug' => 'awards']);

    return AwardCategory::factory()
        ->for($program)
        ->status($status)
        ->create(['slug' => 'cat', 'voting_mode' => VotingMode::Hybrid]);
}

/**
 * @param  array<string, mixed>  $input
 */
function addNominee(AwardCategory $category, User $actor, array $input): Nominee
{
    return app(CreateNomineeAction::class)->execute($category, $actor, $input);
}

it('creates a nominee with a 4:5 WebP portrait and zero votes', function (): void {
    $category = rosterCategory();
    $staff = organizer(OrganizationRole::Staff, $category->organization);

    $nominee = addNominee($category, $staff, [
        'name' => 'Ama Mensah',
        'photo' => UploadedFile::fake()->image('ama.jpg', 1200, 900),
    ]);

    $path = str_replace(Storage::disk('public')->url(''), '', (string) $nominee->avatar_url);
    $stored = Storage::disk('public')->get($path);
    $info = getimagesizefromstring((string) $stored);

    expect($nominee->votes_count)
        ->toBe(0)
        ->and($path)
        ->toEndWith('.webp')
        ->and($info[0] ?? null)
        ->toBe(800)
        ->and($info[1] ?? null)
        ->toBe(1000)
        ->and($info['mime'] ?? null)
        ->toBe('image/webp');
});

it('rejects a file that is not really an image, whatever its name', function (): void {
    $category = rosterCategory();
    $owner = organizer(OrganizationRole::Owner, $category->organization);

    expect(fn() => addNominee($category, $owner, [
        'name' => 'Kofi',
        'photo' => UploadedFile::fake()->createWithContent('kofi.jpg', '%PDF-1.7 not an image'),
    ]))->toThrow(ValidationException::class);
    expect(Nominee::query()->count())->toBe(0);
});

it('rejects photos over the size limit', function (): void {
    config(['organizations.nominee_photos.max_kilobytes' => 10]);
    $category = rosterCategory();
    $owner = organizer(OrganizationRole::Owner, $category->organization);

    expect(fn() => addNominee($category, $owner, [
        'name' => 'Kofi',
        'photo' => UploadedFile::fake()->image('big.jpg', 2000, 2500)->size(500),
    ]))->toThrow(ValidationException::class);
});

it('generates short, unambiguous codes unique within the program', function (): void {
    $category = rosterCategory();
    $owner = organizer(OrganizationRole::Owner, $category->organization);

    $codes = collect(range(1, 30))->map(static fn(int $i): string => addNominee($category, $owner, [
        'name' => 'Ama Mensah',
    ])->nominee_code);

    expect($codes->unique()->count())->toBe(30);
    $codes->each(static function (string $code): void {
        expect($code)
            ->toMatch('/^[A-Z]{2}[2-9]{2}$/')
            ->and(NomineeCode::isValid($code))
            ->toBeTrue()
            ->and($code)
            ->not->toMatch('/[01OI]/');
    });
    expect($codes->first())->toStartWith('AM');
});

it('normalizes a custom code and refuses look-alike characters or a duplicate', function (): void {
    $category = rosterCategory();
    $owner = organizer(OrganizationRole::Owner, $category->organization);

    expect(addNominee($category, $owner, ['name' => 'Yaw', 'nominee_code' => 'ml 27'])->nominee_code)->toBe('ML27');

    expect(fn() => addNominee($category, $owner, ['name' => 'Esi', 'nominee_code' => 'ML27']))
        ->toThrow(ValidationException::class)
        ->and(fn() => addNominee($category, $owner, ['name' => 'Esi', 'nominee_code' => 'O1']))
        ->toThrow(ValidationException::class);
});

it('lets staff add nominees only in Draft; owners and admins also while Live', function (): void {
    $category = rosterCategory(CategoryStatus::Open);

    expect(fn() => addNominee($category, organizer(OrganizationRole::Staff, $category->organization), [
        'name' => 'Late',
    ]))->toThrow(AuthorizationException::class);
    expect(addNominee($category, organizer(OrganizationRole::Admin, $category->organization), [
        'name' => 'Late',
    ])->exists)->toBeTrue();
});

it('audits a nominee added while voting is live, with its values', function (): void {
    $audit = captureAuditLog();
    $category = rosterCategory(CategoryStatus::Open);

    addNominee($category, organizer(OrganizationRole::Owner, $category->organization), [
        'name' => 'Late Entry',
        'nominee_code' => 'LE22',
    ]);

    $entry = collect($audit->getRecords())->firstWhere('message', 'nominee.created_while_live');
    expect($entry?->context['after'])->toMatchArray(['name' => 'Late Entry', 'nominee_code' => 'LE22']);
});

it('refuses an owner of another organization', function (): void {
    $category = rosterCategory();

    expect(fn() => addNominee($category, organizer(), ['name' => 'X']))->toThrow(AuthorizationException::class);
});

it('archives a nominee: hidden publicly, no new votes, votes kept, audited', function (): void {
    $audit = captureAuditLog();
    $category = rosterCategory(CategoryStatus::Open);
    $category->organization->forceFill(['paystack_subaccount_code' => 'ACCT_test'])->save();
    $nominee = Nominee::factory()->for($category)->create([
        'name' => 'Withdrawn Person',
        'nominee_code' => 'WP22',
        'votes_count' => 40,
    ]);

    app(ArchiveNomineeAction::class)->execute($nominee, organizer(OrganizationRole::Admin, $category->organization));

    $this->get('/org/awards/categories/cat')->assertOk()->assertDontSee('Withdrawn Person');
    $this->get('/org/awards/WP22')->assertNotFound();
    expect(fn() => app(QuotePaidVotesAction::class)->execute($nominee->fresh() ?? $nominee, 5))
        ->toThrow(CheckoutUnavailableException::class)
        ->and(fn() => app(CastFreeVoteAction::class)->execute(
            $nominee->fresh() ?? $nominee,
            new VoterIdentity(str_repeat('a', 64), str_repeat('b', 64)),
        ))
        ->toThrow(FreeVoteUnavailableException::class)
        ->and($nominee->fresh()?->votes_count)
        ->toBe(40)
        ->and($audit->hasInfoThatContains('nominee.archived'))
        ->toBeTrue();
});

it('refuses staff archiving and a second archive', function (): void {
    $category = rosterCategory(CategoryStatus::Open);
    $nominee = Nominee::factory()->for($category)->create();
    $admin = organizer(OrganizationRole::Admin, $category->organization);

    expect(fn() => app(ArchiveNomineeAction::class)->execute($nominee, organizer(
        OrganizationRole::Staff,
        $category->organization,
    )))
        ->toThrow(AuthorizationException::class);

    app(ArchiveNomineeAction::class)->execute($nominee, $admin);
    expect(fn() => app(ArchiveNomineeAction::class)->execute($nominee, $admin))->toThrow(AuthorizationException::class);
});
