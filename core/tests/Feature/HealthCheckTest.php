<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_minimal_safe_ok_without_internal_leakage(): void
    {
        $response = $this->getJson('/up');

        $response->assertStatus(200)
            ->assertExactJson([
                'status' => 'ok',
            ]);

        // Security verification: verify NO diagnostic info is exposed (ASVS 5.0)
        $data = $response->json();
        $this->assertArrayNotHasKey('env', $data, 'Health check must not expose environment name.');
        $this->assertArrayNotHasKey('version', $data, 'Health check must not expose application version.');
        $this->assertArrayNotHasKey('checks', $data, 'Health check must not expose database/cache diagnostic checks.');
    }
}
