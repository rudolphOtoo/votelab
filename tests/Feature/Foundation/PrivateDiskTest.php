<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
 * The local disk holds uploaded nominee originals (NOMINEE_PHOTO_ORIGINAL_DISK, private). Laravel's
 * /storage/{path} route for it is off ('serve' => false).
 */

it('registers no route that serves the private local disk', function (): void {
    expect(config('filesystems.disks.local.serve'))
        ->toBeFalse()
        ->and(Route::has('storage.local'))
        ->toBeFalse()
        ->and(Route::has('storage.local.upload'))
        ->toBeFalse();
});

it('never serves a private original over HTTP', function (): void {
    Storage::fake('local', ['serve' => false]);
    Storage::disk('local')->put('nominee-originals/secret.jpg', 'original bytes');

    $this->get('/storage/nominee-originals/secret.jpg')->assertNotFound()->assertDontSee('original bytes');
});
