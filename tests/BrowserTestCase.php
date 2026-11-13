<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Support\Facades\Vite;

/**
 * Real-browser tests (pestphp/pest-plugin-browser, Playwright). Unlike feature tests they load the real CSS
 * and JS: always the production build in public/build (run `npm run build` first), never a running Vite dev
 * server, so screenshots and Lighthouse-like checks see what voters get.
 */
abstract class BrowserTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withVite();
        Vite::useHotFile(storage_path('framework/browser-tests-never-hot'));
    }
}
