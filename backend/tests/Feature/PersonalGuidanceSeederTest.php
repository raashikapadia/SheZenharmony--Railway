<?php

namespace Tests\Feature;

use App\Models\PersonalGuidance;
use Database\Seeders\PersonalGuidanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalGuidanceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_published_starter_content_of_every_type(): void
    {
        $this->seed(PersonalGuidanceSeeder::class);

        $this->assertGreaterThanOrEqual(10, PersonalGuidance::query()->count());
        $this->assertTrue(PersonalGuidance::query()->visible()->where('type', 'affirmation')->exists());
        $this->assertTrue(PersonalGuidance::query()->visible()->where('type', 'quote')->whereNotNull('author')->exists());
        $this->assertTrue(PersonalGuidance::query()->visible()->where('type', 'guidance')->exists());
        $this->assertTrue(PersonalGuidance::query()->visible()->where('type', 'tip')->exists());
    }

    public function test_seeder_is_a_noop_when_content_already_exists(): void
    {
        PersonalGuidance::query()->create([
            'type' => 'affirmation', 'content' => 'Admin owns this now.', 'status' => 'draft',
        ]);

        $this->seed(PersonalGuidanceSeeder::class);

        $this->assertDatabaseCount('personal_guidance', 1);
    }
}
