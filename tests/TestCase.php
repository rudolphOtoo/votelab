<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render without a compiled Vite manifest, so tests never depend on `npm run build`.
        $this->withoutVite();

        // Tests never touch the network: any Laravel HTTP call not faked by the test fails.
        Http::preventStrayRequests();
    }
}
