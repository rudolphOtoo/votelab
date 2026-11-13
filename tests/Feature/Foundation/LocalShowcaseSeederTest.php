<?php

declare(strict_types=1);

use App\Actions\Admin\RevealWinnerAction;
use App\Actions\Admin\TallyAudit;
use App\Actions\Reporting\RecordRefundAction;
use App\Enums\CategoryStatus;
use App\Enums\TransactionStatus;
use App\Http\Middleware\RequireTwoFactorForManagers;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Models\AwardProgram;
use App\Models\Nominee;
use App\Models\Organization;
use App\Models\User;
use App\Models\VoteTransaction;
use Database\Seeders\LocalShowcaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function seedShowcase(): AwardProgram
{
    Storage::fake('public');
    Storage::fake('local');
    Mail::fake();
    // Small portraits: the same pipeline (original, re-encode, every variant) at a fraction of the cost.
    config([
        'organizations.nominee_photos.width' => 80,
        'organizations.nominee_photos.height' => 100,
        'organizations.nominee_photos.variant_widths' => [40, 24],
    ]);
    app()->detectEnvironment(static fn(): string => 'local');

    test()->seed(LocalShowcaseSeeder::class);

    return AwardProgram::query()->where('slug', LocalShowcaseSeeder::PROGRAM)->sole();
}

it('refuses to run anywhere but APP_ENV=local', function (string $environment): void {
    app()->detectEnvironment(static fn(): string => $environment);

    // Even with --force (which skips production's confirmation prompt).
    expect(fn() => Artisan::call('db:seed', ['--class' => LocalShowcaseSeeder::class, '--force' => true]))
        ->toThrow(RuntimeException::class, 'APP_ENV=local');
    expect(User::query()->count())->toBe(0)->and(Organization::query()->count())->toBe(0);
})->with(['production', 'staging', 'testing']);

it('seeds every screen: each category state, a revealed tie, portraits, branding and three roles', function (): void {
    $program = seedShowcase();
    $categories = $program->categories()->get()->keyBy('name');

    // Every lifecycle state, open both with results shown and hidden.
    expect($categories->pluck('status')->unique()->values()->all())->toEqualCanonicalizing(CategoryStatus::cases());

    $tie = $categories['Best Dressed']->nominees()->orderByDesc('votes_count')->limit(2)->pluck('votes_count');
    expect($categories['Best Dressed']->status)
        ->toBe(CategoryStatus::Revealed)
        ->and($tie[0])
        ->toBe($tie[1])
        ->toBeGreaterThan(0);

    // Every nominee has a portrait and both srcset variants, and one name is very long.
    $nominees = Nominee::query()->where('award_program_id', $program->id)->get();
    $nominees->each(function (Nominee $nominee): void {
        expect($nominee->avatar_url)
            ->not
            ->toBeNull()
            ->and(array_keys($nominee->avatar_variants ?? []))
            ->toEqualCanonicalizing(config('organizations.nominee_photos.variant_widths'));
        Storage::disk('local')->assertExists((string) $nominee->photo_original_path);
    });
    expect($nominees->max(static fn(Nominee $nominee): int => mb_strlen($nominee->name)))->toBeGreaterThan(40);
    expect(Nominee::query()->whereIn(
        'award_category_id',
        $categories->where('status', CategoryStatus::Draft)->pluck('id'),
    )->sum('votes_count'))
        ->toBe(0);

    // Branding, a second organization for the switcher, and three roles who can sign in and aren't locked out.
    $organization = $program->organization;
    expect($organization->logo_path)
        ->not
        ->toBeNull()
        ->and($organization->settings['brand_color'] ?? null)
        ->toBe('#8b1e3f');
    Storage::disk('public')->assertExists((string) $organization->logo_path);

    foreach (LocalShowcaseSeeder::ACCOUNTS as $email => $account) {
        $user = User::query()->where('email', $email)->sole();
        expect(Hash::check(LocalShowcaseSeeder::PASSWORD, $user->password))
            ->toBeTrue()
            ->and($user->roleIn($organization))
            ->toBe($account['role'])
            ->and(RequireTwoFactorForManagers::deadline($user)->isFuture())
            ->toBeTrue();
    }

    expect(User::query()->where('email', 'owner@votelab.test')->sole()->organizations()->count())->toBe(2);
});

it('seeds payments in every state with zero drift, one refund waiting and a genuine history', function (): void {
    $program = seedShowcase();

    expect(TallyAudit::run(AwardProgram::query()->get(), dryRun: true))->toBeEmpty();

    $statuses = VoteTransaction::query()->pluck('status')->unique()->all();
    expect($statuses)->toContain(
        TransactionStatus::Successful,
        TransactionStatus::Pending,
        TransactionStatus::Failed,
        TransactionStatus::PaidUncredited,
    );

    foreach ([
        'VL_SHOWCASE_SUCCESS',
        'VL_SHOWCASE_PENDING',
        'VL_SHOWCASE_FAILED',
        'VL_SHOWCASE_UNCREDITED',
    ] as $reference) {
        expect(VoteTransaction::query()->where('payment_reference', $reference)->exists())->toBeTrue($reference);
    }

    // Credited payments are in the ledger; uncounted ones are not.
    VoteTransaction::query()->each(function (VoteTransaction $transaction): void {
        $ledger = (int) $transaction->voteRecord()->sum('quantity');
        expect($ledger)->toBe($transaction->status === TransactionStatus::Successful ? $transaction->quantity : 0);
    });

    $owner = User::query()->where('email', 'owner@votelab.test')->sole();
    expect(app(RecordRefundAction::class)->queue($program, $owner)->pluck('payment_reference')->all())->toBe([
        'VL_SHOWCASE_UNCREDITED',
    ]);

    // The closed category still has a payment being confirmed, so its reveal sheet shows the block.
    $closed = AwardCategory::query()->where('status', CategoryStatus::Closed)->sole();
    expect(RevealWinnerAction::pendingPaymentsCount($closed))->toBe(1);

    expect(AuditEvent::query()->pluck('action')->unique()->all())
        ->toContain('organization.settings_changed', 'category.status_changed', 'category.revealed', 'refund.recorded');
});
