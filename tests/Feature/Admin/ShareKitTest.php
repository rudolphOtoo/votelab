<?php

declare(strict_types=1);

use App\Actions\Admin\CreateNomineeAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\ProgramStatus;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Support\ShareImages;
use App\Support\ShareLinks;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
});

function sharedNominee(CategoryStatus $status = CategoryStatus::Open): Nominee
{
    $organization = Organization::factory()->create(['slug' => 'org', 'name' => 'Accra Creatives']);
    $program = AwardProgram::factory()->for($organization)->create(['slug' => 'awards', 'title' => 'Campus Awards']);
    $category = AwardCategory::factory()
        ->for($program)
        ->status($status)
        ->create(['slug' => 'cat', 'name' => 'Best Artist']);
    $nominee = app(CreateNomineeAction::class)->execute($category, organizer(OrganizationRole::Owner, $organization), [
        'name' => 'Ama Mensah',
        'nominee_code' => 'AM27',
        'photo' => UploadedFile::fake()->image('ama.jpg', 800, 1000),
    ]);
    $nominee->forceFill(['votes_count' => 975_311])->save();

    return $nominee;
}

it('renders the three campaign images at their sizes and caches them', function (
    string $format,
    int $width,
    int $height,
): void {
    $nominee = sharedNominee();

    $response = $this->get("/org/awards/AM27/share/{$format}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $size = getimagesizefromstring((string) $response->streamedContent());

    expect([$size[0] ?? 0, $size[1] ?? 0])
        ->toBe([$width, $height])
        ->and(ShareImages::path($nominee, $format))
        ->toBe(ShareImages::path($nominee->fresh() ?? $nominee, $format))
        ->and(Storage::disk('public')->allFiles("share/{$nominee->id}"))
        ->toHaveCount(1);
})->with([
    ['og',     1200, 630],
    ['square', 1080, 1080],
    ['story',  1080, 1920],
]);

it('offers the WhatsApp Status image as a download', function (): void {
    sharedNominee();

    $this->get('/org/awards/AM27/share/story?download=1')->assertOk()->assertDownload('AM27-story.jpg');
});

it('builds the WhatsApp link with the code and deep link, and no counts', function (): void {
    $share = ShareLinks::for(sharedNominee());
    $text = rawurldecode(substr($share['whatsapp'], strlen('https://wa.me/?text=')));

    expect($share['whatsapp'])
        ->toStartWith('https://wa.me/?text=')
        ->and($text)
        ->toContain('AM27')
        ->toContain('Ama Mensah')
        ->toContain('Best Artist')
        ->toContain($share['url'])
        ->and($share['url'])
        ->toEndWith('/org/awards/AM27')
        ->and($text)
        ->not->toContain('975')
        ->not->toContain('vote count');
});

it('never draws counts: the image depends only on photo, name, code, category and program', function (): void {
    $nominee = sharedNominee();
    $path = ShareImages::path($nominee, 'og');

    $nominee->forceFill(['votes_count' => 1])->save();

    expect(ShareImages::path($nominee->fresh() ?? $nominee, 'og'))->toBe($path);
});

it('serves no image for hidden or withdrawn nominees', function (Closure $hide): void {
    $nominee = sharedNominee();
    $hide($nominee);

    $this->get('/org/awards/AM27/share/og')->assertNotFound();
})->with([
    'archived' => [fn(Nominee $n) => $n->forceFill(['archived_at' => now()])->save()],
    'draft category' => [fn(Nominee $n) => AwardCategory::query()->update(['status' => CategoryStatus::Draft])],
    'unpublished program' => [fn(Nominee $n) => AwardProgram::query()->update(['status' => ProgramStatus::Draft])],
]);

it('previews a shared deep link with Open Graph tags and no counts, even while results are hidden', function (CategoryStatus $status): void {
    sharedNominee($status);

    $html = (string) $this->get('/org/awards/AM27')->assertOk()->getContent();
    preg_match_all('/<meta property="(?:og|twitter):[^>]+>/', $html, $tags);
    $meta = implode("\n", $tags[0]);

    expect($meta)
        ->toContain('<meta property="og:title" content="Vote for Ama Mensah (AM27)">')
        ->toContain('/org/awards/AM27/share/og')
        ->not->toContain('975')
        ->not->toContain('votes');

    if (!$status->isLeaderboardPublic()) {
        expect($html)->not->toContain('975311')->not->toContain('975,311');
    }
})->with([CategoryStatus::Open, CategoryStatus::Embargoed]);

it('serves no image for an archived program', function (): void {
    sharedNominee();
    AwardProgram::query()->update(['status' => ProgramStatus::Archived]);

    $this->get('/org/awards/AM27/share/og')->assertNotFound();
});

it('rate limits the image route per network', function (): void {
    config(['voting.rate_limits.share_images_per_ip' => 3]);
    sharedNominee();

    foreach (range(1, 3) as $_) {
        $this->get('/org/awards/AM27/share/og')->assertOk();
    }

    $this->get('/org/awards/AM27/share/og')->assertTooManyRequests();
});
