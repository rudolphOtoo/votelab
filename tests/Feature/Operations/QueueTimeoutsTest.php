<?php

declare(strict_types=1);

use App\Jobs\FulfillPaidVoteOrderJob;
use App\Jobs\GenerateNomineePhotoVariantsJob;
use App\Mail\OrganizationInvitationMail;
use App\Mail\OrganizationSettingsChangedMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Notifications\QueuedResetPassword;
use App\Support\Queues;
use Illuminate\Support\Carbon;

/*
 * Named queues (cleanup batch 4): payments never wait behind a slow job, and no job outlives retry_after, after
 * which the queue hands a still-running job to a second worker.
 */

it('puts each queued job and email on its own queue', function (): void {
    $invitation = OrganizationInvitation::factory()->make();

    expect(new FulfillPaidVoteOrderJob('VL_1')->queue)
        ->toBe(Queues::PAYMENTS)
        ->and(new GenerateNomineePhotoVariantsJob([1])->queue)
        ->toBe(Queues::IMAGES)
        ->and(new OrganizationInvitationMail($invitation, 'https://example.test/accept')->queue)
        ->toBe(Queues::MAIL)
        ->and(new OrganizationSettingsChangedMail(Organization::factory()->make(), 'Ama', Carbon::now(), [])->queue)
        ->toBe(Queues::MAIL)
        ->and(new QueuedResetPassword('token')->queue)
        ->toBe(Queues::MAIL);
});

it('keeps every job timeout below the queue retry_after', function (): void {
    $retryAfter = (int) config('queue.connections.database.retry_after');

    expect($retryAfter)
        ->toBe(90)
        ->and(new FulfillPaidVoteOrderJob('VL_1')->timeout)
        ->toBeLessThan($retryAfter)
        ->and(new GenerateNomineePhotoVariantsJob([1])->timeout)
        ->toBeLessThan($retryAfter);
});

it('documents worker commands that cover every queue, payments first and images on their own', function (): void {
    $readme = (string) file_get_contents(base_path('README.md'));

    expect($readme)
        ->toContain('queue:work --queue=' . Queues::PAYMENTS . ',' . Queues::MAIL . ',default ')
        ->toContain('queue:work --queue=' . Queues::IMAGES . ' ')
        ->toContain('stopwaitsecs=90');
});
