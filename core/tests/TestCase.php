<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel 11+ removed the framework's own createApplication(): an
     * application skeleton is expected to provide it. Without this the suite
     * ran with no booted app, so tests that only rendered static markup
     * "passed" while anything touching the container (a database-backed
     * catalogue, for example) failed with "no such table" or a 500.
     */
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
