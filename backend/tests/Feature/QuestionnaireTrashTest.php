<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireTrashTest extends TestCase
{
    use RefreshDatabase;

    private int $version = 0;

    private function trashed(string $title = 'Trashed', ?\DateTimeInterface $purgeAfter = null): Questionnaire
    {
        $questionnaire = Questionnaire::query()->create([
            'title' => $title, 'type' => 'stress', 'version' => ++$this->version, 'status' => 'archived', 'is_active' => false,
            'trashed_at' => now()->subDay(),
            'purge_after' => $purgeAfter ?? now()->addDays(Questionnaire::TRASH_RETENTION_DAYS),
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'S', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);
        $questionnaire->scoreBands()->create(['code' => 'a', 'label' => 'A', 'min_score' => 0, 'max_score' => 5, 'is_active' => true]);

        return $questionnaire;
    }

    public function test_admin_can_restore_a_trashed_questionnaire(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = $this->trashed();

        $this->actingAs($admin)
            ->patch(route('admin.questionnaires.restore', $questionnaire->id))
            ->assertRedirect(route('admin.questionnaires.trash'));

        $questionnaire->refresh();
        $this->assertNull($questionnaire->trashed_at);
        $this->assertNull($questionnaire->purge_after);
        $this->assertSame('archived', $questionnaire->status);

        $this->actingAs($admin)->get(route('admin.questionnaires.index'))->assertOk()->assertSee('Trashed');
    }

    public function test_admin_can_permanently_delete_a_trashed_questionnaire_now(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = $this->trashed();
        $sectionId = $questionnaire->sections()->value('id');

        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.force-destroy', $questionnaire->id))
            ->assertRedirect(route('admin.questionnaires.trash'));

        $this->assertDatabaseMissing('questionnaires', ['id' => $questionnaire->id]);
        $this->assertDatabaseMissing('questionnaire_sections', ['id' => $sectionId]);
        $this->assertDatabaseMissing('stress_score_bands', ['questionnaire_id' => $questionnaire->id]);
    }

    public function test_force_delete_of_a_questionnaire_with_history_restores_it_instead(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = $this->trashed();
        StressAssessment::query()->create([
            'user_id' => $admin->id, 'questionnaire_id' => $questionnaire->id,
            'assessment_status' => 'completed', 'total_score' => 1, 'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.force-destroy', $questionnaire->id))
            ->assertRedirect();

        $this->assertDatabaseHas('questionnaires', ['id' => $questionnaire->id, 'trashed_at' => null]);
    }

    public function test_scheduled_purge_removes_only_expired_trash_without_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $expired = $this->trashed('Expired', now()->subDay());
        $stillWaiting = $this->trashed('Waiting', now()->addDays(3));
        $expiredWithHistory = $this->trashed('Expired with history', now()->subDay());
        StressAssessment::query()->create([
            'user_id' => $admin->id, 'questionnaire_id' => $expiredWithHistory->id,
            'assessment_status' => 'completed', 'total_score' => 1, 'completed_at' => now(),
        ]);

        $this->artisan('shezen:purge-questionnaires')->assertSuccessful();

        $this->assertDatabaseMissing('questionnaires', ['id' => $expired->id]);
        $this->assertDatabaseHas('questionnaires', ['id' => $stillWaiting->id]);
        // History appeared -> kept and un-trashed.
        $this->assertDatabaseHas('questionnaires', ['id' => $expiredWithHistory->id, 'trashed_at' => null]);
    }

    public function test_dry_run_purge_deletes_nothing(): void
    {
        $expired = $this->trashed('Expired', now()->subDay());

        $this->artisan('shezen:purge-questionnaires --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('questionnaires', ['id' => $expired->id]);
    }

    public function test_students_cannot_touch_the_trash_endpoints(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $questionnaire = $this->trashed();

        $this->actingAs($student)->get(route('admin.questionnaires.trash'))->assertForbidden();
        $this->actingAs($student)->patch(route('admin.questionnaires.restore', $questionnaire->id))->assertForbidden();
        $this->actingAs($student)->delete(route('admin.questionnaires.force-destroy', $questionnaire->id))->assertForbidden();
    }
}
