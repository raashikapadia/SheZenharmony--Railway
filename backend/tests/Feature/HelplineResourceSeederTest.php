<?php

namespace Tests\Feature;

use App\Models\HelplineResource;
use Database\Seeders\HelplineResourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelplineResourceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_publishes_starter_helplines_in_display_order(): void
    {
        $this->seed(HelplineResourceSeeder::class);

        $names = HelplineResource::query()->visible()->inDisplayOrder()->pluck('name')->all();

        $this->assertSame(['USP Student Counselling', 'National Crisis Helpline'], $names);
    }

    public function test_seeded_helplines_reach_the_student_app(): void
    {
        $this->seed(HelplineResourceSeeder::class);

        $this->getJson('/api/v1/helplines')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'USP Student Counselling')
            ->assertJsonPath('data.1.is_emergency', true);
    }

    public function test_seeder_is_a_noop_when_an_admin_already_published_contacts(): void
    {
        HelplineResource::query()->create([
            'name' => 'Admin owns this now',
            'phone' => '000',
            'is_active' => false,
        ]);

        $this->seed(HelplineResourceSeeder::class);

        $this->assertDatabaseCount('helpline_resources', 1);
    }
}
