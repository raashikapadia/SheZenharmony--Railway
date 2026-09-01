<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\WellbeingActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_wellbeing_activities_are_returned(): void
    {
        WellbeingActivity::query()->create([
            'title' => 'Grounding pause',
            'description' => 'A short grounding activity.',
            'video_url' => 'https://example.com/grounding',
            'video_type' => 'youtube',
            'category' => 'Grounding',
            'is_active' => true,
        ]);
        WellbeingActivity::query()->create([
            'title' => 'Hidden draft',
            'video_url' => 'https://example.com/draft',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/wellbeing-activities')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Grounding pause')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.created_by_user_id');
    }

    public function test_interventions_can_be_filtered_for_positive_engagement(): void
    {
        Intervention::query()->create([
            'title' => 'Kind reflection',
            'slug' => 'kind-reflection',
            'content_type' => 'affirmation',
            'instructions' => 'Name one thing you handled well today.',
            'is_active' => true,
        ]);
        Intervention::query()->create([
            'title' => 'General resource',
            'slug' => 'general-resource',
            'content_type' => 'resource',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/interventions?content_type=affirmation,quiz')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Kind reflection')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.created_by_user_id');
    }

    public function test_breathing_and_journaling_content_can_be_routed_to_student_sections(): void
    {
        foreach ([
            ['title' => 'Box breathing', 'slug' => 'box-breathing', 'content_type' => 'breathing'],
            ['title' => 'Gratitude reflection', 'slug' => 'gratitude-reflection', 'content_type' => 'journaling'],
        ] as $content) {
            Intervention::query()->create($content + ['is_active' => true]);
        }

        $this->getJson('/api/v1/interventions?content_type=breathing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Box breathing');

        $this->getJson('/api/v1/interventions?content_type=journaling')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Gratitude reflection');
    }
}
