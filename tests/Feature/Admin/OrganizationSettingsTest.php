<?php

declare(strict_types=1);

use App\Actions\Admin\UpdateOrganizationSettingsAction;
use App\Enums\OrganizationRole;
use App\Mail\OrganizationSettingsChangedMail;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Mail::fake();
});

/**
 * @param  array<string, mixed>  $input
 */
function updateSettings(Organization $organization, User $actor, array $input, bool $confirmed = true): Organization
{
    return app(UpdateOrganizationSettingsAction::class)->execute($organization, $actor, $input, $confirmed);
}

it('lets owners and admins change the name, without emailing owners', function (OrganizationRole $role): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    updateSettings($organization, organizer($role, $organization), ['name' => 'Renamed Events'], confirmed: false);

    expect($organization->fresh()?->name)->toBe('Renamed Events');
    Mail::assertNothingQueued();
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

it('refuses staff profile changes and admin slug or payout changes', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    expect(fn() => updateSettings($organization, organizer(OrganizationRole::Staff, $organization), ['name' => 'X']))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => updateSettings($organization, organizer(OrganizationRole::Admin, $organization), [
            'slug' => 'new-slug',
        ]))
        ->toThrow(AuthorizationException::class)
        ->and(fn() => updateSettings($organization, organizer(OrganizationRole::Admin, $organization), [
            'paystack_subaccount_code' => 'ACCT_x1',
        ]))
        ->toThrow(AuthorizationException::class);
});

it('needs a confirmed password for slug and payout changes', function (string $field, string $value): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    expect(fn() => updateSettings($organization, $owner, [$field => $value], confirmed: false))
        ->toThrow(ValidationException::class);
    expect($organization->fresh()?->getAttribute($field))->not->toBe($value);
})->with([
    ['slug',                     'fresh-slug'],
    ['paystack_subaccount_code', 'ACCT_abc123'],
]);

it('emails every owner who changed the payout account and when, and audits before/after', function (): void {
    $audit = captureAuditLog();
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();
    $organization->forceFill(['paystack_subaccount_code' => 'ACCT_old111'])->save();
    $coOwner = organizer(OrganizationRole::Owner, $organization);
    organizer(OrganizationRole::Admin, $organization);

    updateSettings($organization, $owner, ['paystack_subaccount_code' => 'ACCT_new222']);

    Mail::assertQueued(OrganizationSettingsChangedMail::class, 2);
    foreach ([$owner, $coOwner] as $recipient) {
        Mail::assertQueued(
            OrganizationSettingsChangedMail::class,
            static fn(OrganizationSettingsChangedMail $mail): bool => (
                $mail->hasTo($recipient->email)
                && str_contains($mail->changedBy, $owner->email)
                && $mail->changes === ['Paystack payout account: ACCT_old111 → ACCT_new222']
            ),
        );
    }

    $entry = collect($audit->getRecords())->firstWhere('message', 'organization.settings_changed');
    expect($entry?->context['before'])
        ->toBe(['paystack_subaccount_code' => 'ACCT_old111'])
        ->and($entry?->context['after'])
        ->toBe(['paystack_subaccount_code' => 'ACCT_new222']);
});

it('emails owners on a slug change', function (): void {
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    updateSettings($organization, $owner, ['slug' => 'brand-new-slug']);

    expect($organization->fresh()?->slug)->toBe('brand-new-slug');
    Mail::assertQueued(OrganizationSettingsChangedMail::class, 1);
});

it('rejects reserved, malformed and taken slugs, and malformed payout codes', function (array $input): void {
    Organization::factory()->create(['slug' => 'taken']);
    $owner = organizer();

    expect(fn() => updateSettings($owner->organizations()->firstOrFail(), $owner, $input))
        ->toThrow(ValidationException::class);
    Mail::assertNothingQueued();
})->with([
    'reserved' => [['slug' => 'dashboard']],
    'malformed' => [['slug' => 'Not A Slug']],
    'taken' => [['slug' => 'taken']],
    'payout' => [['paystack_subaccount_code' => 'not-a-code']],
]);

it('does nothing (no email, no audit) when nothing changes', function (): void {
    $audit = captureAuditLog();
    $owner = organizer();
    $organization = $owner->organizations()->firstOrFail();

    updateSettings($organization, $owner, ['slug' => $organization->slug]);

    Mail::assertNothingQueued();
    expect($audit->getRecords())->toBeEmpty();
});
