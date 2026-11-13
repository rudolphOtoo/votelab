<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Public pages may be restored from the back/forward cache (D-118); Livewire sends `no-store` for every
 * component by default. Livewire calls this on page loads and on every update, so update responses don't
 * block the cache either. Signed-in pages never use it.
 */
trait AllowsBackForwardCache
{
    public function bootAllowsBackForwardCache(): void
    {
        $this->enableBackButtonCache();
    }
}
