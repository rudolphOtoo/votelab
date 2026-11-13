<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Enums\TransactionStatus;
use App\Livewire\Admin\CategoryManager;
use App\Livewire\Admin\NomineeManager;
use App\Livewire\Admin\ResultsAuditor;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\RefundRecord;
use App\Models\User;
use App\Models\VoteTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * @return array{AwardProgram, AwardCategory, Nominee, User}
 */
function screenFixture(CategoryStatus $status = CategoryStatus::Open): array
{
    $owner = organizer();
    $program = AwardProgram::factory()->for($owner->organizations()->firstOrFail())->create([
        'title' => 'Campus Awards',
    ]);
    $category = AwardCategory::factory()->for($program)->status($status)->create(['name' => 'Best Artist']);
    $nominee = Nominee::factory()->for($category)->create(['name' => 'Ama Mensah', 'votes_count' => 864_213]);

    return [$program, $category, $nominee, $owner];
}

/**
 * @return list<string>
 */
function programUrls(AwardProgram $program, AwardCategory $category): array
{
    return [
        "/dashboard/programs/{$program->id}",
        "/dashboard/programs/{$program->id}/categories",
        "/dashboard/programs/{$program->id}/categories/{$category->id}/nominees",
        "/dashboard/programs/{$program->id}/results",
    ];
}

it('opens every program screen for the owner', function (): void {
    [$program, $category, , $owner] = screenFixture();

    foreach ([
        ...programUrls($program, $category),
        "/dashboard/programs/{$program->id}/results?tab=transactions",
        "/dashboard/programs/{$program->id}/results?tab=refunds",
        "/dashboard/programs/{$program->id}/results?tab=revenue",
        "/dashboard/programs/{$program->id}/results?tab=history",
    ] as $url) {
        $this->actingAs($owner)->get($url)->assertOk();
    }
});

it('refuses every program screen and download to an owner of another organization', function (): void {
    [$program, $category, $nominee] = screenFixture();
    Storage::fake('local');
    $nominee->forceFill(['photo_original_path' => 'nominee-originals/x.jpg'])->save();
    Storage::disk('local')->put('nominee-originals/x.jpg', 'x');
    $outsider = organizer();

    foreach ([
        ...programUrls($program, $category),
        "/dashboard/programs/{$program->id}/export",
        "/dashboard/nominees/{$nominee->id}/original",
    ] as $url) {
        $this->actingAs($outsider)->get($url)->assertForbidden();
    }
    $this
        ->actingAs($outsider)
        ->get("/dashboard/organizations/{$program->organization->slug}/programs/new")
        ->assertForbidden();
});

it('404s a category from another program under this program', function (): void {
    [$program, , , $owner] = screenFixture();
    $foreign = AwardCategory::factory()->create();

    $this
        ->actingAs($owner)
        ->get("/dashboard/programs/{$program->id}/categories/{$foreign->id}/nominees")
        ->assertNotFound();
});

it('shows staff the screens they may use, without money tabs or hidden counts', function (): void {
    [$program, $category] = screenFixture(CategoryStatus::Embargoed);
    $staff = organizer(OrganizationRole::Staff, $program->organization);

    $this
        ->actingAs($staff)
        ->get("/dashboard/programs/{$program->id}/categories")
        ->assertOk()
        ->assertDontSee('Add category');
    $results = $this->actingAs($staff)->get("/dashboard/programs/{$program->id}/results?tab=transactions")->assertOk();
    $results
        ->assertSee('Results are hidden')
        ->assertDontSee('864,213')
        ->assertDontSee('Transactions')
        ->assertDontSee('Export');
    $this->actingAs($staff)->get("/dashboard/programs/{$program->id}/export")->assertForbidden();

    Livewire::actingAs($staff)
        ->test(ResultsAuditor::class, ['program' => $program])
        ->set('search', 'x@example.com')
        ->call('lookup')
        ->assertForbidden();
});

it('shows owners and admins the live counts while results are hidden', function (): void {
    [$program, , , $owner] = screenFixture(CategoryStatus::Embargoed);

    $this->actingAs($owner)->get("/dashboard/programs/{$program->id}/results")->assertOk()->assertSee('864,213');
});

it('creates a category with the price entered in cedis, stored in pesewas', function (): void {
    [$program, , , $owner] = screenFixture();

    Livewire::actingAs($owner)
        ->test(CategoryManager::class, ['program' => $program])
        ->call('create')
        ->set('form.name', 'Best Dancer')
        ->set('form.slug', 'best-dancer')
        ->set('form.price', '2.50')
        ->call('save')
        ->assertHasNoErrors();

    expect(AwardCategory::query()->where('slug', 'best-dancer')->sole()->price_per_vote)->toBe(250);
});

it('rejects a price that is not cedis with up to two decimals', function (): void {
    [$program, , , $owner] = screenFixture();

    Livewire::actingAs($owner)
        ->test(CategoryManager::class, ['program' => $program])
        ->call('create')
        ->set('form.name', 'X')
        ->set('form.slug', 'x')
        ->set('form.price', '2.505')
        ->call('save')
        ->assertHasErrors('form.price');
});

it('asks for the password before revealing anyway, then reveals', function (): void {
    [$program, $category, $nominee, $owner] = screenFixture(CategoryStatus::Closed);
    $category->forceFill(['closed_at' => now(), 'close_grace_minutes' => 30])->save();
    VoteTransaction::factory()->for($nominee)->create(['created_at' => now()->subSeconds(30)]);

    Livewire::actingAs($owner)
        ->test(CategoryManager::class, ['program' => $program])
        ->call('reveal', $category->id)
        ->assertHasErrors('status');
    expect($category->fresh()?->status)->toBe(CategoryStatus::Closed);

    Livewire::actingAs($owner)
        ->test(CategoryManager::class, ['program' => $program])
        ->set('revealAnyway', true)
        ->call('reveal', $category->id)
        ->assertRedirect(route('password.confirm'));
    expect($category->fresh()?->status)->toBe(CategoryStatus::Closed);

    confirmPassword();
    Livewire::actingAs($owner)
        ->test(CategoryManager::class, ['program' => $program])
        ->set('revealAnyway', true)
        ->call('reveal', $category->id);
    expect($category->fresh()?->status)->toBe(CategoryStatus::Revealed);
});

it('changes status through the screen only where the matrix allows', function (): void {
    [$program, $category, , $owner] = screenFixture(CategoryStatus::Open);
    $staff = organizer(OrganizationRole::Staff, $program->organization);

    Livewire::actingAs($staff)
        ->test(CategoryManager::class, ['program' => $program])
        ->call('changeStatus', $category->id, 'embargoed')
        ->assertForbidden();
    Livewire::actingAs($owner)
        ->test(CategoryManager::class, ['program' => $program])
        ->call('changeStatus', $category->id, 'embargoed');

    expect($category->fresh()?->status)->toBe(CategoryStatus::Embargoed);
});

it('adds a nominee with an uploaded, cropped photo', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    [$program, $category, , $owner] = screenFixture(CategoryStatus::Draft);

    Livewire::actingAs($owner)
        ->test(NomineeManager::class, ['program' => $program, 'category' => $category])
        ->call('open')
        ->set('form.name', 'Kofi Boateng')
        ->set('form.photo', UploadedFile::fake()->image('kofi.jpg', 1200, 1200))
        ->set('form.crop', ['x' => 0.1, 'y' => 0.0, 'width' => 0.6])
        ->call('save')
        ->assertHasNoErrors();

    $nominee = Nominee::query()->where('name', 'Kofi Boateng')->sole();
    expect($nominee->photo_crop)
        ->toEqual(['x' => 0.1, 'y' => 0, 'width' => 0.6])
        ->and($nominee->avatar_url)
        ->not->toBeNull();
});

it('records a refund from the screen only after a password check', function (): void {
    [$program, , $nominee, $owner] = screenFixture();
    $transaction = VoteTransaction::factory()->for($nominee)->create(['status' => TransactionStatus::PaidUncredited]);

    Livewire::actingAs($owner)
        ->test(ResultsAuditor::class, ['program' => $program])
        ->set('tab', 'refunds')
        ->call('startRefund', $transaction->id)
        ->set('refundReason', 'Refunded via Paystack')
        ->call('recordRefund')
        ->assertRedirect(route('password.confirm'));
    expect(RefundRecord::query()->count())->toBe(0);

    confirmPassword();
    Livewire::actingAs($owner)
        ->test(ResultsAuditor::class, ['program' => $program])
        ->set('tab', 'refunds')
        ->call('startRefund', $transaction->id)
        ->set('refundReason', 'Refunded via Paystack')
        ->set('refundReference', 'RFND_1')
        ->call('recordRefund')
        ->assertHasNoErrors();
    expect(RefundRecord::query()->sole()->paystack_refund_reference)->toBe('RFND_1');
});

it('audits a transaction lookup made from the screen', function (): void {
    [$program, , , $owner] = screenFixture();

    Livewire::actingAs($owner)
        ->test(ResultsAuditor::class, ['program' => $program])
        ->set('tab', 'transactions')
        ->set('search', 'nobody@example.com')
        ->call('lookup');

    expect(AuditEvent::query()->where('action', 'transactions.lookup')->exists())->toBeTrue();
});

it('downloads the CSV export for admins', function (): void {
    [$program, , , $owner] = screenFixture();
    $admin = organizer(OrganizationRole::Admin, $program->organization);

    $response = $this->actingAs($admin)->get("/dashboard/programs/{$program->id}/export")->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('.csv')
        ->and($response->streamedContent())
        ->toContain('Nominee Code');
});

it('links programs from the dashboard and offers New program only to those who can create', function (): void {
    [$program, , , $owner] = screenFixture();
    $staff = organizer(OrganizationRole::Staff, $program->organization);

    $this
        ->actingAs($owner)
        ->get('/dashboard')
        ->assertSee(route('dashboard.programs.show', $program), false)
        ->assertSee('New program');
    $this->actingAs($staff)->get('/dashboard')->assertDontSee('New program');
});
