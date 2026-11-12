<?php

declare(strict_types=1);

use App\Enums\CategoryStatus;

it('links a field to its hint and marks it valid when there is no error', function (): void {
    $html = (string) $this->blade('<x-ui.field name="email" label="Email" hint="For your receipt" />');

    expect($html)
        ->toContain('<label for="field-email"')
        ->toContain('id="field-email"')
        ->toContain('aria-describedby="field-email-hint"')
        ->not->toContain('aria-invalid="true"');
});

it('announces a field error and marks the control invalid', function (): void {
    $html = (string) $this->withViewErrors(['nominee_code' => 'Use 2–8 capital letters.'])->blade(
        '<x-ui.field name="nominee_code" label="Code" hint="e.g. AM27" />',
    );

    expect($html)
        ->toContain('aria-invalid="true"')
        ->toContain('aria-describedby="field-nominee-code-hint field-nominee-code-error"')
        ->toContain('id="field-nominee-code-error"')
        ->toContain('Use 2–8 capital letters.');
});

it('lists every error in the summary with a link to its field', function (): void {
    $html = (string) $this->withViewErrors(['email' => 'Enter an email.', 'nominee_code' => 'Unknown code.'])->blade(
        '<x-ui.error-summary />',
    );

    expect($html)->toContain('role="alert"')->toContain('href="#field-email"')->toContain('href="#field-nominee-code"');
});

it('renders no error summary when there are no errors', function (): void {
    expect(trim((string) $this->blade('<x-ui.error-summary />')))->toBeEmpty();
});

it('describes category status in voter language', function (CategoryStatus $status, string $copy): void {
    expect((string) $this->blade('<x-ui.status-pill :status="$s" />', ['s' => $status]))
        ->toContain($copy)
        ->not->toContain('mbargo');
})->with([
    [CategoryStatus::Draft,     'Coming soon'],
    [CategoryStatus::Open,      'Voting open'],
    [CategoryStatus::Embargoed, 'Voting open · results hidden'],
    [CategoryStatus::Closed,    'Voting closed'],
    [CategoryStatus::Revealed,  'Winner announced'],
]);

it('renders sheets as labelled modal dialogs', function (): void {
    $html = (string) $this->blade(
        '<x-ui.sheet name="confirm" title="Close voting?" description="This cannot be undone.">Body</x-ui.sheet>',
    );

    expect($html)
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-labelledby="sheet-confirm-title"')
        ->toContain('aria-describedby="sheet-confirm-description"')
        ->toContain('x-trap.noscroll.inert');
});

it('renders a table as stacked cards on phones and a captioned table from 640 px', function (): void {
    $html = (string) $this->blade(
        '<x-ui.table caption="Results" :columns="[\'name\' => \'Nominee\', \'votes\' => \'Votes\']" :numeric="[\'votes\']" :rows="[[\'name\' => \'<b>Ama</b>\', \'votes\' => \'12\']]" />',
    );

    expect($html)
        ->toMatch('/<ul class="space-y-2 sm:hidden"\s+aria-label="Results"/')
        ->toContain('<caption class="sr-only">Results</caption>')
        ->toContain('<th scope="col"')
        ->toContain('text-right tabular-nums')
        ->toContain('&lt;b&gt;Ama&lt;/b&gt;')
        ->not->toContain('<b>Ama</b>');
    expect((string) $this->blade('<x-ui.table :columns="[]" :rows="[]" empty="No votes yet." />'))
        ->toContain('No votes yet.');
});

it('formats money from integer pesewas', function (): void {
    expect((string) $this->blade('<x-ui.money :pesewas="125_000" />'))->toContain(App\Support\Money::format(125_000));
});

it('gives buttons a 44 px minimum target and renders links as anchors', function (): void {
    expect((string) $this->blade('<x-ui.button>Vote</x-ui.button>'))
        ->toContain('type="submit"')
        ->toMatch('/min-h-1[12]/')
        ->and((string) $this->blade('<x-ui.button href="/x">Go</x-ui.button>'))
        ->toContain('<a href="/x"');
});

it('exposes progress and toggles to assistive technology', function (): void {
    expect((string) $this->blade('<x-ui.progress label="Checklist" :value="4" :max="6" />'))
        ->toContain('role="progressbar"')
        ->toContain('aria-valuenow="4"')
        ->toContain('aria-valuemax="6"')
        ->and((string) $this->blade('<x-ui.toggle label="Hide results" :checked="true" />'))
        ->toContain('role="switch"');
});

it('paginates with the first and last pages and the current page in context', function (): void {
    $paginator = new Illuminate\Pagination\LengthAwarePaginator([], 120, 10, 6, ['path' => '/results']);
    $html = (string) $this->blade('<x-ui.pagination :paginator="$p" />', ['p' => $paginator]);

    expect($html)
        ->toContain('aria-label="Pagination"')
        ->toContain('aria-label="Page 1"')
        ->toContain('aria-label="Page 5"')
        ->toContain('<span aria-current="page"')
        ->toContain('aria-label="Page 7"')
        ->toContain('aria-label="Page 12"')
        ->not
        ->toContain('aria-label="Page 3"')
        ->toContain('Page 6 of 12')
        ->and(substr_count($html, '…'))
        ->toBe(2);
});
