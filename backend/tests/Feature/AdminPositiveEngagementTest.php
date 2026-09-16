<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPositiveEngagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_published_positive_engagement_for_students(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('admin.positive-engagement.create'))
            ->assertOk()
            ->assertDontSee('value="affirmation"', false)
            ->assertSee('value="motivation"', false)
            ->assertDontSee('value="positive_engagement"', false)
            ->assertDontSee('value="journaling"', false);

        $this->actingAs($admin)->post(route('admin.positive-engagement.store'), [
            'title' => 'Daily motivation',
            'description' => 'Try one positive action today.',
            'content_type' => 'motivation',
            'instructions' => 'Pick the response you would offer a friend.',
            'is_active' => '1',
            'all_levels' => '1',
        ])->assertRedirect(route('admin.positive-engagement.index'));

        $item = Intervention::query()->where('title', 'Daily motivation')->sole();

        $this->actingAs($admin)->get(route('admin.positive-engagement.index'))
            ->assertOk()
            ->assertSee('Daily motivation')
            ->assertSee('Published');

        $this->getJson('/api/v1/interventions?content_type=quiz,motivation,positive_engagement')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Daily motivation')
            ->assertJsonPath('data.0.content_type', 'motivation');

        $this->actingAs($admin)->put(route('admin.positive-engagement.update', $item), [
            'title' => 'A calmer inner voice',
            'description' => 'Try one kind thought today.',
            'content_type' => 'motivation',
            'instructions' => 'Choose one small achievable action.',
            'is_active' => '1',
            'all_levels' => '1',
        ])->assertRedirect(route('admin.positive-engagement.index'));

        $this->assertDatabaseHas('interventions', [
            'id' => $item->id,
            'title' => 'A calmer inner voice',
            'content_type' => 'motivation',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.positive-engagement.destroy', $item))
            ->assertRedirect();
        $this->assertDatabaseMissing('interventions', ['id' => $item->id]);
    }

    public function test_positive_engagement_admin_does_not_manage_affirmations_or_journaling(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $journal = Intervention::query()->create([
            'title' => 'Journal prompt',
            'content_type' => 'journaling',
            'is_active' => true,
        ]);
        $legacyAffirmation = Intervention::query()->create([
            'title' => 'Legacy affirmation',
            'content_type' => 'affirmation',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.positive-engagement.index'))
            ->assertOk()
            ->assertDontSee('Journal prompt')
            ->assertDontSee('Legacy affirmation');
        $this->actingAs($admin)
            ->get(route('admin.positive-engagement.edit', $journal))
            ->assertNotFound();
        $this->actingAs($admin)
            ->get(route('admin.positive-engagement.edit', $legacyAffirmation))
            ->assertNotFound();
        $this->actingAs($admin)->post(route('admin.positive-engagement.store'), [
            'title' => 'Wrong section',
            'content_type' => 'journaling',
        ])->assertSessionHasErrors('content_type');
        $this->actingAs($admin)->post(route('admin.positive-engagement.store'), [
            'title' => 'Wrong section affirmation',
            'content_type' => 'affirmation',
        ])->assertSessionHasErrors('content_type');

        $this->assertDatabaseHas('interventions', [
            'id' => $journal->id,
            'content_type' => 'journaling',
        ]);
        $this->assertDatabaseHas('interventions', [
            'id' => $legacyAffirmation->id,
            'content_type' => 'affirmation',
        ]);
    }

    public function test_admin_quiz_is_available_to_the_student_app_when_active(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('admin.positive-engagement.games-quizzes.store'), [
            'name' => 'Grounding check-in',
            'category' => 'Wellbeing',
            'description' => 'A short check-in.',
            'status' => 'active',
        ])->assertRedirect();

        $quiz = Quiz::query()->where('name', 'Grounding check-in')->sole();

        $this->getJson('/api/v1/positive-engagement/quizzes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $quiz->id)
            ->assertJsonPath('data.0.name', 'Grounding check-in');
    }

    public function test_positive_engagement_admin_routes_remain_protected(): void
    {
        $this->get(route('admin.positive-engagement.index'))
            ->assertRedirect(route('admin.login'));

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($student)
            ->get(route('admin.positive-engagement.index'))
            ->assertForbidden();
    }
}
