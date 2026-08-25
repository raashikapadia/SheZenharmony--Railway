<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminQuestionnaireApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_use_questionnaire_administration_api(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        $this->getJson('/api/v1/admin/questionnaires')->assertForbidden();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/v1/admin/questionnaires', [
            'title' => 'Draft questionnaire',
            'description' => 'API test',
            'period' => 'Weekly',
            'status' => 'draft',
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('questionnaires', [
            'title' => 'Draft questionnaire',
            'status' => 'draft',
            'is_active' => false,
        ]);
    }

    public function test_create_and_update_cannot_activate_an_incomplete_questionnaire(): void
    {
        $admin = $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/questionnaires', [
            'title' => 'Incomplete create',
            'status' => 'published',
            'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('questions');

        $questionnaire = Questionnaire::query()->create([
            'title' => 'Incomplete update', 'type' => 'stress', 'version' => 2,
            'status' => 'draft', 'is_active' => false, 'created_by_user_id' => $admin->id,
        ]);

        $this->putJson("/api/v1/admin/questionnaires/{$questionnaire->id}", [
            'title' => $questionnaire->title,
            'status' => 'published',
            'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('questions');

        $this->assertFalse($questionnaire->fresh()->is_active);
    }

    public function test_activation_requires_complete_non_overlapping_score_coverage(): void
    {
        $admin = $this->actingAsAdmin();
        $questionnaire = $this->configuredQuestionnaire($admin, 1, [[0, 1], [3, 4]]);

        $this->patchJson("/api/v1/admin/questionnaires/{$questionnaire->id}/activate")
            ->assertUnprocessable()->assertJsonValidationErrors('score_bands');

        $this->assertFalse($questionnaire->fresh()->is_active);
    }

    public function test_activation_archives_the_previous_active_version_of_the_same_type(): void
    {
        $admin = $this->actingAsAdmin();
        $first = $this->configuredQuestionnaire($admin, 1, [[0, 4]]);
        $second = $this->configuredQuestionnaire($admin, 2, [[0, 4]]);

        $this->patchJson("/api/v1/admin/questionnaires/{$first->id}/activate")->assertOk();
        $this->patchJson("/api/v1/admin/questionnaires/{$second->id}/activate")->assertOk();

        $this->assertDatabaseHas('questionnaires', ['id' => $first->id, 'status' => 'archived', 'is_active' => false]);
        $this->assertDatabaseHas('questionnaires', ['id' => $second->id, 'status' => 'published', 'is_active' => true]);
        $this->assertSame(1, Questionnaire::query()->where('type', 'stress')->where('is_active', true)->count());
    }

    public function test_admin_can_manage_questions_and_cross_questionnaire_access_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Question API', 'type' => 'stress', 'version' => 1,
            'status' => 'draft', 'is_active' => false, 'created_by_user_id' => $admin->id,
        ]);

        $response = $this->postJson("/api/v1/admin/questionnaires/{$questionnaire->id}/questions", [
            'question_text' => 'DEMO: How tense do you feel?',
            'question_type' => 'scale',
            'is_required' => true,
            'options' => [
                ['label' => 'Never', 'value' => '0', 'score' => 0],
                ['label' => 'Often', 'value' => '4', 'score' => 4],
            ],
        ])->assertCreated();

        $questionId = $response->json('data.id');
        $other = Questionnaire::query()->create([
            'title' => 'Other', 'type' => 'other', 'version' => 1,
            'status' => 'draft', 'is_active' => false, 'created_by_user_id' => $admin->id,
        ]);

        $this->deleteJson("/api/v1/admin/questionnaires/{$other->id}/questions/{$questionId}")->assertNotFound();
        $this->deleteJson("/api/v1/admin/questionnaires/{$questionnaire->id}/questions/{$questionId}")->assertOk();
    }

    public function test_score_band_api_rejects_overlap_and_cross_questionnaire_access(): void
    {
        $admin = $this->actingAsAdmin();
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Band API', 'type' => 'stress', 'version' => 1,
            'status' => 'draft', 'is_active' => false, 'created_by_user_id' => $admin->id,
        ]);
        $other = Questionnaire::query()->create([
            'title' => 'Other bands', 'type' => 'other', 'version' => 1,
            'status' => 'draft', 'is_active' => false, 'created_by_user_id' => $admin->id,
        ]);

        $response = $this->postJson("/api/v1/admin/questionnaires/{$questionnaire->id}/score-bands", [
            'code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 4,
        ])->assertCreated();
        $bandId = $response->json('data.id');

        $this->postJson("/api/v1/admin/questionnaires/{$questionnaire->id}/score-bands", [
            'code' => 'overlap', 'label' => 'Overlap', 'min_score' => 4, 'max_score' => 8,
        ])->assertUnprocessable();

        $this->deleteJson("/api/v1/admin/questionnaires/{$other->id}/score-bands/{$bandId}")->assertNotFound();
    }

    public function test_active_questionnaire_structure_cannot_be_mutated_in_place(): void
    {
        $admin = $this->actingAsAdmin();
        $questionnaire = $this->configuredQuestionnaire($admin, 1, [[0, 4]]);
        $this->patchJson("/api/v1/admin/questionnaires/{$questionnaire->id}/activate")->assertOk();
        $question = $questionnaire->questions()->firstOrFail();
        $band = $questionnaire->scoreBands()->firstOrFail();

        $this->deleteJson("/api/v1/admin/questionnaires/{$questionnaire->id}/questions/{$question->id}")
            ->assertConflict();
        $this->deleteJson("/api/v1/admin/questionnaires/{$questionnaire->id}/score-bands/{$band->id}")
            ->assertConflict();

        $this->assertDatabaseHas('questionnaire_questions', [
            'questionnaire_id' => $questionnaire->id,
            'stress_question_id' => $question->id,
        ]);
        $this->assertDatabaseHas('stress_score_bands', ['id' => $band->id, 'is_active' => true]);
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin, ['admin']);

        return $admin;
    }

    private function configuredQuestionnaire(User $admin, int $version, array $ranges): Questionnaire
    {
        $questionnaire = Questionnaire::query()->create([
            'title' => "Configured {$version}", 'type' => 'stress', 'version' => $version,
            'status' => 'draft', 'is_active' => false, 'created_by_user_id' => $admin->id,
        ]);
        $question = StressQuestion::query()->create([
            'code' => "configured-{$version}", 'question_text' => 'DEMO: Test question',
            'question_type' => 'scale', 'position' => 1, 'is_active' => true,
            'is_required' => true, 'is_sensitive' => false, 'created_by_user_id' => $admin->id,
        ]);
        foreach ([0, 1, 2, 3, 4] as $position => $score) {
            $question->options()->create([
                'label' => (string) $score, 'value' => (string) $score,
                'score' => $score, 'position' => $position + 1, 'is_active' => true,
            ]);
        }
        $questionnaire->questions()->attach($question, ['position' => 1, 'is_required' => true]);
        foreach ($ranges as $position => [$minimum, $maximum]) {
            $questionnaire->scoreBands()->create([
                'code' => "range-{$position}", 'label' => "Range {$position}",
                'min_score' => $minimum, 'max_score' => $maximum,
                'position' => $position + 1, 'is_active' => true,
                'created_by_user_id' => $admin->id,
            ]);
        }

        return $questionnaire;
    }
}
