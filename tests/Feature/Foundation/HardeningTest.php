<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

it('prevents lazy loading outside production', function (): void {
    expect(app()->isProduction())->toBeFalse()->and(Model::preventsLazyLoading())->toBeTrue();
});

it('indexes audit_events by organization and time', function (): void {
    $indexes = collect(Schema::getIndexes('audit_events'))->pluck('columns')->all();

    expect($indexes)->toContain(['organization_id', 'created_at']);
});
