<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthApiTest extends TestCase
{
    public function test_health_endpoint_reports_that_the_api_is_available(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'success',
                'service' => 'SheZen API',
                'message' => 'Laravel backend is connected.',
            ]);
    }
}
