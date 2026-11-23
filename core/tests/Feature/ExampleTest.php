<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Smoke test: the home page responds successfully.
     *
     * The home page is now database-driven (Sprint 1), so it needs a migrated
     * database and seeded catalogue like any other storefront page. The seeding
     * is what makes this a meaningful smoke test rather than a check that the
     * framework can emit a 500.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->seed(\Database\Seeders\ProductsSeeder::class);
        $this->seed(\Database\Seeders\SongsSeeder::class);

        $this->get('/')->assertStatus(200);
    }
}
