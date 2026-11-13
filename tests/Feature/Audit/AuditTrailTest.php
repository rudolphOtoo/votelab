<?php

declare(strict_types=1);

use App\Actions\Admin\TransitionCategoryStatusAction;
use App\Enums\CategoryStatus;
use App\Enums\OrganizationRole;
use App\Models\AuditEvent;
use App\Models\AwardCategory;
use App\Support\Audit;
use Symfony\Component\Finder\Finder;

it('writes each audit entry to the audit_events table and copies it to the audit log', function (): void {
    $log = captureAuditLog();
    $category = AwardCategory::factory()->status(CategoryStatus::Open)->create();
    $admin = organizer(OrganizationRole::Admin, $category->organization);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.23']);
    app(TransitionCategoryStatusAction::class)->execute($category, CategoryStatus::Embargoed, $admin, 'Final week');

    $event = AuditEvent::query()->sole();
    expect($event->action)
        ->toBe('category.status_changed')
        ->and($event->actor_user_id)
        ->toBe($admin->id)
        ->and($event->organization_id)
        ->toBe($category->organization_id)
        ->and($event->subject_type)
        ->toBe('award_category')
        ->and($event->subject_id)
        ->toBe($category->id)
        ->and($event->before)
        ->toBe(['status' => 'open'])
        ->and($event->after)
        ->toBe(['status' => 'embargoed'])
        ->and($event->context)
        ->toBe(['reason' => 'Final week'])
        ->and($event->created_at)
        ->not->toBeNull();

    $copy = collect($log->getRecords())->firstWhere('message', 'category.status_changed');
    expect($copy?->context['audit_event_id'])->toBe($event->id);
});

it('records the organizer\'s IP address', function (): void {
    $category = AwardCategory::factory()->create();
    $this->get('/up'); // A request context, as in the app.
    request()->server->set('REMOTE_ADDR', '203.0.113.7');

    Audit::record('test.event', null, $category);

    expect(AuditEvent::query()->sole()->ip_address)->toBe('203.0.113.7');
});

it('refuses updates and deletes through the model', function (): void {
    $event = Audit::record('test.event', null, AwardCategory::factory()->create());

    expect(fn() => $event->forceFill(['action' => 'tampered'])->save())
        ->toThrow(LogicException::class)
        ->and($event->delete(...))
        ->toThrow(LogicException::class);
    expect(AuditEvent::query()->sole()->action)->toBe('test.event');
});

it('has no app code that updates or deletes audit events through the query builder', function (): void {
    $offenders = [];

    foreach (new Finder()
        ->files()
        ->in(app_path())
        ->name('*.php') as $file) {
        $code = $file->getContents();

        if (
            preg_match('/AuditEvent::query\(\)[^;]*->(update|delete|forceDelete|truncate|upsert)\(/s', $code) === 1
            || preg_match("/table\\(\\s*'audit_events'\\s*\\)[^;]*->(update|delete|truncate)\\(/s", $code) === 1
        ) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBeEmpty();
});
