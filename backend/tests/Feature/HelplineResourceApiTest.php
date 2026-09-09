<?php

namespace Tests\Feature;

use App\Models\HelplineResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelplineResourceApiTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $overrides = []): HelplineResource
    {
        return HelplineResource::query()->create(array_merge([
            'name' => 'Counselling Service',
            'phone' => '555 0100',
            'is_active' => true,
        ], $overrides));
    }

    public function test_it_returns_only_active_resources(): void
    {
        $this->make(['name' => 'Published line']);
        $this->make(['name' => 'Hidden line', 'is_active' => false]);

        $response = $this->getJson('/api/v1/helplines')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Published line');
    }

    public function test_the_admin_display_order_decides_and_emergency_does_not_jump_the_queue(): void
    {
        $this->make(['name' => 'Campus counsellor', 'position' => 0]);
        $this->make(['name' => 'Aftercare line', 'position' => 2]);
        $this->make(['name' => 'Emergency services', 'position' => 1, 'is_emergency' => true]);

        $names = $this->getJson('/api/v1/helplines')->assertOk()->json('data.*.name');

        // The emergency contact sits where the admin put it; the flag only
        // tints and badges its card in the app.
        $this->assertSame(['Campus counsellor', 'Emergency services', 'Aftercare line'], $names);
    }

    public function test_equal_positions_fall_back_to_alphabetical_order(): void
    {
        $this->make(['name' => 'Zephyr line']);
        $this->make(['name' => 'Anchor line']);

        $names = $this->getJson('/api/v1/helplines')->assertOk()->json('data.*.name');

        $this->assertSame(['Anchor line', 'Zephyr line'], $names);
    }

    public function test_it_exposes_the_contact_fields_the_app_renders(): void
    {
        $this->make([
            'name' => 'National Crisis Helpline',
            'organisation' => 'Ministry of Health',
            'description' => 'Free and confidential.',
            'phone' => '1325',
            'alternate_phone' => '1326',
            'email' => 'help@example.test',
            'website_url' => 'https://example.test',
            'availability' => '24 hours, 7 days',
            'category' => 'Crisis',
            'is_emergency' => true,
        ]);

        $this->getJson('/api/v1/helplines')->assertOk()->assertJsonStructure([
            'data' => [['id', 'name', 'organisation', 'description', 'phone', 'alternate_phone',
                'email', 'website_url', 'availability', 'category', 'is_emergency']],
        ]);
    }
}
