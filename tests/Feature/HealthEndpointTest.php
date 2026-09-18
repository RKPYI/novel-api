<?php

namespace Tests\Feature;

class HealthEndpointTest extends FeatureTestCase
{
    public function test_health_endpoint_reports_application_status(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'up')
            ->assertJsonPath('checks.database', 'healthy')
            ->assertJsonPath('checks.cache', 'healthy')
            ->assertJsonPath('checks.storage', 'healthy');
    }
}
