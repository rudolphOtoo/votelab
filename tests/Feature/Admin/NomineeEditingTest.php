<?php

declare(strict_types=1);

use App\Actions\Admin\CreateNomineeAction;
use App\Actions\Admin\UpdateNomineeAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Support\ShareImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

function editNominee(Nominee $nominee, App\Models\User $actor, array $input): Nominee
{
    return app(UpdateNomineeAction::class)->execute($nominee, $actor, $input);
}

function photographedNominee(CategoryStatus $status = CategoryStatus::Draft): array
{
    $category = AwardCategory::factory()->status($status)->create();
    $owner = organizer(OrganizationRole::Owner, $category->organization);
    $nominee = app(CreateNomineeAction::class)->execute($category, $owner, [
        'name' => 'Ama Mensah',
        'photo' => UploadedFile::fake()->image('ama.jpg', 1000, 1000),
        'crop' => ['x' => 0.0, 'y' => 0.0, 'width' => 0.5],
    ]);

    return [$nominee, $owner, $category];
}

it('keeps the original privately and stores the organizer\'s crop', function (): void {
    [$nominee] = photographedNominee();

    expect($nominee->photo_original_path)
        ->toStartWith('nominee-originals/')
        ->and(Storage::disk('local')->exists((string) $nominee->photo_original_path))
        ->toBeTrue()
        ->and(Storage::disk('public')->exists((string) $nominee->photo_original_path))
        ->toBeFalse()
        ->and($nominee->photo_crop)
        ->toEqual(['x' => 0, 'y' => 0, 'width' => 0.5]);
});

it('re-crops from the original without a new upload', function (): void {
    [$nominee, $owner] = photographedNominee();
    $before = $nominee->avatar_url;

    $updated = editNominee($nominee, $owner, ['crop' => ['x' => 0.3, 'y' => 0.2, 'width' => 0.5]]);

    expect($updated->avatar_url)
        ->not
        ->toBe($before)
        ->and($updated->photo_original_path)
        ->toBe($nominee->photo_original_path)
        ->and($updated->photo_crop)
        ->toEqual(['x' => 0.3, 'y' => 0.2, 'width' => 0.5])
        ->and(AuditEvent::query()->where('action', 'nominee.updated')->sole()->context['photo'])
        ->toBe('recropped');
});

it('clamps a crop that would fall outside the photo', function (): void {
    [$nominee, $owner] = photographedNominee();

    $crop = editNominee($nominee, $owner, ['crop' => ['x' => 0.9, 'y' => 0.9, 'width' => 3]])->photo_crop;

    // 1000x1000 source: the widest 4:5 frame is 0.8 wide and the full height.
    expect($crop)->toEqual(['x' => 0.2, 'y' => 0, 'width' => 0.8]);
});

it('lets staff change photo and bio while Live but not the name, code or category', function (): void {
    [$nominee, , $category] = photographedNominee(CategoryStatus::Open);
    $staff = organizer(OrganizationRole::Staff, $category->organization);

    expect(editNominee($nominee, $staff, ['bio' => 'New bio'])->bio)
        ->toBe('New bio')
        ->and(fn() => editNominee($nominee, $staff, ['name' => 'Renamed']))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => editNominee($nominee, $staff, ['stage_name' => 'Stage']))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => editNominee($nominee, $staff, ['nominee_code' => 'ZZ22']))
        ->toThrow(AuthorizationException::class);
});

it('audits a Live rename with before and after', function (): void {
    [$nominee, $owner] = photographedNominee(CategoryStatus::Open);

    editNominee($nominee, $owner, ['name' => 'Ama K. Mensah']);

    $event = AuditEvent::query()->where('action', 'nominee.updated')->sole();
    expect($event->before)
        ->toBe(['name' => 'Ama Mensah'])
        ->and($event->after)
        ->toBe(['name' => 'Ama K. Mensah'])
        ->and($event->context['while_live'])
        ->toBeTrue();
});

it('changes the code only in Draft, with the same rules as new codes', function (): void {
    [$nominee, $owner] = photographedNominee();

    expect(editNominee($nominee, $owner, ['nominee_code' => 'ak 27'])->nominee_code)
        ->toBe('AK27')
        ->and(fn() => editNominee($nominee, $owner, ['nominee_code' => 'O1']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
});

it('clears cached share images when the name, code or photo changes', function (): void {
    [$nominee, $owner] = photographedNominee(CategoryStatus::Open);
    $first = ShareImages::path($nominee, 'square');
    expect(Storage::disk('public')->exists($first))->toBeTrue();

    editNominee($nominee, $owner, ['name' => 'Someone Else']);

    $second = ShareImages::path($nominee->fresh() ?? $nominee, 'square');
    expect(Storage::disk('public')->exists($first))->toBeFalse()->and($second)->not->toBe($first);
});
