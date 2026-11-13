<?php

declare(strict_types=1);

use App\Actions\Admin\UpdateOrganizationSettingsAction;
use App\Enums\OrganizationRole;
use App\Livewire\Admin\OrganizationSettings;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Support\Design\Contrast;
use App\Support\Design\StageBranding;
use App\Support\ShareImages;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// D-131: brand colour and logo upload in organization settings.

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * @param  array<string, mixed>  $input
 */
function brandSettings(Organization $organization, User $actor, array $input): Organization
{
    return app(UpdateOrganizationSettingsAction::class)->execute(
        $organization,
        $actor,
        $input,
        passwordConfirmed: false,
    );
}

function pngWithAlpha(int $width, int $height): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 14, 107, 94, 60));
    $path = tempnam(sys_get_temp_dir(), 'logo') . '.png';
    imagepng($image, $path);

    return new UploadedFile($path, 'logo.png', 'image/png', null, true);
}

it('saves the brand colour lower-case, audited, for owners and admins only', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    brandSettings($organization, organizer(OrganizationRole::Admin, $organization), ['brand_color' => '#8B1E3F']);

    expect($organization->refresh()->settings['brand_color'])->toBe('#8b1e3f');
    $event = AuditEvent::query()->where('action', 'organization.settings_changed')->latest('id')->firstOrFail();
    expect($event->before)->toBe(['brand_color' => null])->and($event->after)->toBe(['brand_color' => '#8b1e3f']);

    expect(fn() => brandSettings($organization, organizer(OrganizationRole::Staff, $organization), [
        'brand_color' => '#000000',
    ]))->toThrow(AuthorizationException::class);

    brandSettings($organization, $owner, ['brand_color' => null]);
    expect($organization->refresh()->settings['brand_color'])->toBeNull();
});

it('accepts only #rrggbb colours', function (string $color): void {
    $owner = organizer();

    expect(fn() => brandSettings($owner->organizations()->firstOrFail(), $owner, [
        'brand_color' => $color,
    ]))->toThrow(ValidationException::class);
})->with(['red', '#fff', '8b1e3f', '#8b1e3g', '#8b1e3f00', '#8b1e3f; } body { display:none']);

it('clears cached share images when the colour, logo or name changes', function (array $input): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $nominee = Nominee::factory()->for(AwardCategory::factory()->for(App\Models\AwardProgram::factory()->for(
        $organization,
    )))->create();
    $path = ShareImages::path($nominee, 'square');
    expect(ShareImages::disk()->exists($path))->toBeTrue();

    brandSettings($organization, $owner, $input);

    expect(ShareImages::disk()->exists($path))
        ->toBeFalse()
        ->and(ShareImages::path($nominee->fresh(), 'square'))
        ->not->toBe($path);
})->with([
    'colour' => [['brand_color' => '#8b1e3f']],
    'name' => [['name' => 'Renamed Creatives']],
    'logo' => [fn() => ['logo' => pngWithAlpha(300, 300)]],
]);

it('re-encodes an uploaded logo as WebP within 512 px, keeping transparency', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    brandSettings($organization, $owner, ['logo' => pngWithAlpha(1200, 600)]);
    $organization->refresh();
    $bytes = (string) Storage::disk('public')->get((string) $organization->logo_path);
    $info = getimagesizefromstring($bytes);

    expect($organization->logo_path)
        ->toStartWith('organization-logos/')
        ->toEndWith('.webp')
        ->and($organization->logo_url)
        ->toContain($organization->logo_path)
        ->and($info['mime'] ?? null)
        ->toBe('image/webp')
        ->and([$info[0] ?? 0, $info[1] ?? 0])
        ->toBe([512, 256])
        ->and(substr($bytes, 0, 4))
        ->toBe('RIFF')
        ->and($bytes)
        ->not->toContain('Exif');
});

it('strips camera metadata from a JPEG logo', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $jpeg = UploadedFile::fake()->image('logo.jpg', 400, 400);
    // A JPEG carrying an EXIF block with a location-like marker.
    $raw = (string) file_get_contents($jpeg->getRealPath());
    $exif = "\xFF\xE1" . pack('n', 28) . "Exif\x00\x00GPS-LOCATION-SECRET!";
    file_put_contents($jpeg->getRealPath(), substr($raw, 0, 2) . $exif . substr($raw, 2));

    brandSettings($organization, $owner, ['logo' => $jpeg]);

    expect((string) Storage::disk('public')->get((string) $organization->refresh()->logo_path))
        ->not
        ->toContain('GPS-LOCATION-SECRET');
});

it('refuses logos that are too big or not images', function (UploadedFile $file, string $message): void {
    $owner = organizer();

    expect(fn() => brandSettings($owner->organizations()->firstOrFail(), $owner, [
        'logo' => $file,
    ]))->toThrow(ValidationException::class, $message);
})->with([
    'too big' => [fn() => UploadedFile::fake()->create('logo.png', 2_100, 'image/png'), 'at most 2 MB'],
    'not an image' => [
        fn() => UploadedFile::fake()->createWithContent('logo.png', 'this is plain text, not a picture'),
        'JPEG, PNG or WebP',
    ],
]);

it('deletes the old upload when the logo is replaced or removed', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    brandSettings($organization, $owner, ['logo' => pngWithAlpha(200, 200)]);
    $first = (string) $organization->refresh()->logo_path;
    brandSettings($organization, $owner, ['logo' => pngWithAlpha(200, 200)]);
    $second = (string) $organization->refresh()->logo_path;

    expect(Storage::disk('public')->exists($first))
        ->toBeFalse()
        ->and(Storage::disk('public')->exists($second))
        ->toBeTrue();

    brandSettings($organization, $owner, ['logo_url' => null]);

    expect($organization->refresh()->logo_path)
        ->toBeNull()
        ->and($organization->logo_url)
        ->toBeNull()
        ->and(Storage::disk('public')->exists($second))
        ->toBeFalse();
});

it('previews the colour live and warns when it falls back to VoteLab', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    Livewire::actingAs($owner)
        ->test(OrganizationSettings::class, ['organization' => $organization])
        ->set('brand_color', '#8b1e3f')
        ->assertSee('--brand: #8b1e3f;', escape: false)
        ->assertDontSee("VoteLab's teal is used instead")
        ->set('brand_color', '#7a7a7a')
        ->assertSee("VoteLab's teal is used instead")
        ->assertSee('--brand: #0e6b5e;', escape: false)
        ->set('brand_color', '#8b1e3f')
        ->call('saveProfile')
        ->assertSee('Name, logo and colour saved.');

    expect($organization->refresh()->settings['brand_color'])->toBe('#8b1e3f');
});

it('gives the stage screen a readable version of every colour', function (string $color): void {
    $organization = Organization::factory()->create(['settings' => ['brand_color' => $color]]);

    expect(Contrast::ratio(StageBranding::accent($organization), '#121316'))->toBeGreaterThanOrEqual(4.5);
})->with(['#000000', '#0e6b5e', '#8b1e3f', '#1d4ed8', '#f2b705', '#ffffff', '#7a7a7a', '#2a0845']);

it('uses the VoteLab gold on stage without a usable colour', function (): void {
    expect(StageBranding::accent(null))
        ->toBe('#fbbf24')
        ->and(StageBranding::accent(Organization::factory()->create(['settings' => ['brand_color' => '#7a7a7a']])))
        ->toBe('#fbbf24');
});
