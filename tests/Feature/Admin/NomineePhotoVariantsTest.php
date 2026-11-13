<?php

declare(strict_types=1);

use App\Actions\Admin\CreateNomineeAction;
use App\Actions\Admin\UpdateNomineeAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Jobs\GenerateNomineePhotoVariantsJob;
use App\Models\AwardCategory;
use App\Models\Nominee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// D-127: 400×500 and 240×300 WebP copies of every portrait, for srcset on public cards.

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * @return array{0: Nominee, 1: App\Models\User, 2: AwardCategory}
 */
function portraitNominee(CategoryStatus $status = CategoryStatus::Open): array
{
    $category = AwardCategory::factory()->status($status)->create();
    $owner = organizer(OrganizationRole::Owner, $category->organization);
    $nominee = app(CreateNomineeAction::class)->execute($category, $owner, [
        'name' => 'Ama Mensah',
        'photo' => UploadedFile::fake()->image('ama.jpg', 1000, 1250),
    ]);

    return [$nominee, $owner, $category];
}

function publicPath(string $url): string
{
    return Illuminate\Support\Str::after((string) parse_url($url, PHP_URL_PATH), '/storage/');
}

/**
 * @return array{0: int, 1: int}
 */
function webpSize(string $url): array
{
    $info = getimagesizefromstring((string) Storage::disk('public')->get(publicPath($url)));

    return [(int) ($info[0] ?? 0), (int) ($info[1] ?? 0)];
}

it('writes the 400 and 240 px variants with the portrait at upload', function (): void {
    [$nominee] = portraitNominee();

    expect($nominee->avatar_variants)
        ->toHaveKeys(['400', '240'])
        ->and(webpSize((string) $nominee->avatar_url))
        ->toBe([800, 1000])
        ->and(webpSize($nominee->avatar_variants['400']))
        ->toBe([400, 500])
        ->and(webpSize($nominee->avatar_variants['240']))
        ->toBe([240, 300])
        ->and(App\Support\NomineePortrait::srcset($nominee))
        ->toBe(
            "{$nominee->avatar_variants['240']} 240w, {$nominee->avatar_variants['400']} 400w, {$nominee->avatar_url} 800w",
        )
        ->and(App\Support\NomineePortrait::smallest($nominee))
        ->toBe($nominee->avatar_variants['240']);

    // Originals stay private; only the public copies are on the public disk.
    expect(Storage::disk('public')->exists((string) $nominee->photo_original_path))->toBeFalse();
});

it('replaces every variant when the photo is re-cropped', function (): void {
    [$nominee, $owner] = portraitNominee();
    $before = $nominee->avatar_variants;

    $updated = app(UpdateNomineeAction::class)->execute($nominee, $owner, [
        'crop' => ['x' => 0.2, 'y' => 0.1, 'width' => 0.6],
    ]);

    expect($updated->avatar_variants['400'])
        ->not->toBe($before['400'])->and($updated->avatar_variants['240'])
        ->not->toBe($before['240'])->and(Storage::disk('public')->exists(publicPath(
            $updated->avatar_variants['240'],
        )))->toBeTrue();
});

it('serves the variants on public cards and in the vote drawer', function (): void {
    [$nominee, , $category] = portraitNominee();
    $program = $category->awardProgram;

    $this
        ->get(route('public.category', [$program->organization, $program, $category]))
        ->assertOk()
        ->assertSee('srcset="' . e((string) App\Support\NomineePortrait::srcset($nominee)) . '"', escape: false)
        ->assertSee('sizes="(min-width: 1024px) 14rem, (min-width: 640px) 30vw, 46vw"', escape: false);

    $this->get(route('public.nominee', [
        $program->organization,
        $program,
        $nominee->nominee_code,
    ]))->assertOk()->assertSee('sizes="56px"', escape: false);
});

it('backfills portraits made before variants existed, chunked, queued and idempotent', function (): void {
    Queue::fake();
    [$a] = portraitNominee();
    [$b] = portraitNominee();
    [$c] = portraitNominee();
    Nominee::query()->update(['avatar_variants' => null]); // as if uploaded before D-127
    Nominee::factory()->create(['avatar_url' => 'https://example.test/legacy.webp', 'photo_original_path' => null]);

    $this
        ->artisan('nominees:photo-variants', ['--dry-run' => true, '--chunk' => 2])
        ->expectsOutputToContain('Would queue 3 nominee(s) in 2 job(s). 1 portrait(s) have no original')
        ->assertSuccessful();
    Queue::assertNothingPushed();

    $this->artisan('nominees:photo-variants', ['--chunk' => 2])->assertSuccessful();
    Queue::assertPushed(GenerateNomineePhotoVariantsJob::class, 2);

    // Run the queued work, then run the command again: nothing left to queue.
    Queue::pushed(GenerateNomineePhotoVariantsJob::class)->each(
        static fn(GenerateNomineePhotoVariantsJob $job) => $job->handle(),
    );

    expect(Nominee::query()->whereKey([$a->id, $b->id, $c->id])->whereNull('avatar_variants')->count())->toBe(0);
    foreach ([$a, $b, $c] as $nominee) {
        expect($nominee->refresh()->avatar_variants)
            ->toHaveKeys(['400', '240'])
            ->and(Storage::disk('public')->exists(publicPath($nominee->avatar_variants['240'])))
            ->toBeTrue();
    }

    $this->artisan('nominees:photo-variants', ['--dry-run' => true])->expectsOutputToContain(
        'Would queue 0 nominee(s)',
    );
});

it('never overwrites variants written by a re-crop made while the backfill was queued', function (): void {
    [$nominee, $owner] = portraitNominee();
    Nominee::query()->update(['avatar_variants' => null]);
    $job = new GenerateNomineePhotoVariantsJob([$nominee->id]);

    $recropped = app(UpdateNomineeAction::class)->execute($nominee->refresh(), $owner, [
        'crop' => ['x' => 0.1, 'y' => 0.1, 'width' => 0.7],
    ]);
    $job->handle();

    expect($recropped->refresh()->avatar_variants)
        ->toEqual($recropped->avatar_variants)
        ->and($recropped->avatar_variants['240'])
        ->toContain(basename(publicPath((string) $recropped->avatar_url), '.webp'));
});
