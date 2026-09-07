<?php

namespace Tests\Feature;

use App\Models\Intervention;
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
            ->assertSee('value="quiz"', false)
            ->assertSee('value="motivation"', false)
            ->assertSee('value="positive_engagement"', false)
            ->assertDontSee('value="journaling"', false);

        $this->actingAs($admin)->post(route('admin.positive-engagement.store'), [
            'title' => 'A kinder inner voice quiz',
            'description' => 'Choose the fairest response.',
            'content_type' => 'quiz',
            'instructions' => 'Pick the response you would offer a friend.',
            'is_active' => '1',
            'all_levels' => '1',
        ])->assertRedirect(route('admin.positive-engagement.index'));

        $item = Intervention::query()->where('title', 'A kinder inner voice quiz')->sole();

        $this->actingAs($admin)->get(route('admin.positive-engagement.index'))
            ->assertOk()
            ->assertSee('A kinder inner voice quiz')
            ->assertSee('Published');

        $this->getJson('/api/v1/interventions?content_type=quiz,motivation,positive_engagement')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'A kinder inner voice quiz')
            ->assertJsonPath('data.0.content_type', 'quiz');

        $this->actingAs($admin)->put(route('admin.positive-engagement.update', $item), [
            'title' => 'Daily motivation',
            'description' => 'Try one positive action today.',
            'content_type' => 'motivation',
            'instructions' => 'Choose one small achievable action.',
            'is_active' => '1',
            'all_levels' => '1',
        ])->assertRedirect(route('admin.positive-engagement.index'));

        $this->assertDatabaseHas('interventions', [
            'id' => $item->id,
            'title' => 'Daily motivation',
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
