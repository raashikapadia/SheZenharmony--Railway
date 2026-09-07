<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\StressResponse;
use App\Models\User;
use App\Services\AssessmentScoringService;
use App\Services\ScaleBandValidator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuestionnaireArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_questionnaire_makes_a_draft_and_opens_the_editor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->post('/admin/questionnaires', [
            'title' => 'Stress check',
            'description' => 'A quick wellbeing check-in.',
        ]);

        $questionnaire = Questionnaire::query()->firstOrFail();

        // Creation is name-only; sections, questions and publishing happen in the editor.
        $response->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));
        $this->assertSame('stress', $questionnaire->type);
        $this->assertSame(1, $questionnaire->version);
        $this->assertSame('draft', $questionnaire->status);
        $this->assertFalse((bool) $questionnaire->is_active);

        // Standard wellbeing result ranges are seeded so the publish step opens pre-filled.
        $bands = $questionnaire->scoreBands()->where('scope', 'overall')->orderBy('min_score')->get();
        $this->assertCount(3, $bands);
        $this->assertSame(0, $bands->first()->min_score);
        $this->assertSame(40, $bands->last()->max_score);
    }

    public function test_new_questionnaire_version_number_follows_the_highest_existing(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Questionnaire::query()->create(['title' => 'Existing', 'type' => 'stress', 'version' => 4]);

        $this->actingAs($admin)->post('/admin/questionnaires', ['title' => 'Next one'])->assertRedirect();

        $this->assertSame(5, Questionnaire::query()->where('title', 'Next one')->value('version'));
    }

    public function test_delete_moves_a_questionnaire_to_trash_with_a_purge_date(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Scrap', 'type' => 'stress', 'version' => 1]);

        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.destroy', $questionnaire))
            ->assertRedirect(route('admin.questionnaires.index'));

        $questionnaire->refresh();
        $this->assertNotNull($questionnaire->trashed_at);
        $this->assertNotNull($questionnaire->purge_after);
        $this->assertEqualsWithDelta(
            now()->addDays(Questionnaire::TRASH_RETENTION_DAYS)->timestamp,
            $questionnaire->purge_after->timestamp,
            60,
        );
        $this->assertSame('archived', $questionnaire->status);
        $this->assertFalse((bool) $questionnaire->is_active);

        // Not counted in the live list; shown in trash.
        $this->assertSame(0, Questionnaire::query()->notInTrash()->count());
        $this->assertSame(1, Questionnaire::query()->inTrash()->count());
        $this->actingAs($admin)->get(route('admin.questionnaires.trash'))->assertOk()->assertSee('Scrap');
    }

    public function test_delete_falls_back_to_archive_when_assessment_history_exists(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Kept', 'type' => 'stress', 'version' => 1, 'status' => 'published', 'is_active' => true]);
        StressAssessment::query()->create([
            'user_id' => $admin->id, 'questionnaire_id' => $questionnaire->id,
            'assessment_status' => 'completed', 'total_score' => 3, 'completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.destroy', $questionnaire))
            ->assertRedirect();

        $this->assertDatabaseHas('questionnaires', [
            'id' => $questionnaire->id, 'status' => 'archived', 'is_active' => false, 'trashed_at' => null,
        ]);
    }

    public function test_archive_action_soft_archives_without_deleting(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Live', 'type' => 'stress', 'version' => 1, 'status' => 'published', 'is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.questionnaires.archive', $questionnaire))
            ->assertRedirect();

        $this->assertDatabaseHas('questionnaires', ['id' => $questionnaire->id, 'status' => 'archived', 'is_active' => false]);
    }

    public function test_duplicate_question_membership_is_prevented(): void
    {
        $questionnaire = Questionnaire::query()->create(['title' => 'One', 'type' => 'stress', 'version' => 1]);
        [$question] = $this->questionWithOptions();
        $questionnaire->questions()->attach($question, ['position' => 1]);

        $this->expectException(QueryException::class);
        $questionnaire->questions()->attach($question, ['position' => 2]);
    }

    public function test_active_endpoint_excludes_inactive_questionnaires_options_and_scores(): void
    {
        $inactive = Questionnaire::query()->create(['title' => 'Old', 'type' => 'stress', 'version' => 1, 'status' => 'archived', 'is_active' => false]);
        $active = Questionnaire::query()->create(['title' => 'Current', 'type' => 'stress', 'version' => 2, 'status' => 'published', 'is_active' => true]);
        [$question, $activeOption, $inactiveOption] = $this->questionWithOptions();
        $active->questions()->attach($question, ['position' => 1, 'is_required' => true]);

        $response = $this->getJson('/api/v1/questionnaires/active')->assertOk()
            ->assertJsonPath('data.id', $active->id)
            ->assertJsonPath('data.questions.0.options.0.id', $activeOption->id)
            ->assertJsonCount(1, 'data.questions.0.options');

        $this->assertArrayNotHasKey('score', $response->json('data.questions.0.options.0'));
        $this->assertNotSame($inactive->id, $response->json('data.id'));
    }

    public function test_question_edit_preserves_historical_option_and_updates_existing_rows(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$question, $used, $removed] = $this->questionWithOptions();
        $assessment = StressAssessment::query()->create(['user_id' => $admin->id]);
        StressResponse::query()->create([
            'stress_assessment_id' => $assessment->id, 'stress_question_id' => $question->id,
            'question_option_id' => $removed->id, 'score' => $removed->score,
        ]);

        $this->actingAs($admin)->put("/admin/questions/{$question->id}", [
            'question_text' => 'Updated', 'question_type' => 'scale', 'position' => 1, 'is_active' => '1',
            'options' => [
                ['id' => $used->id, 'label' => 'Updated option', 'value' => $used->value, 'score' => 3],
                ['label' => 'New option', 'value' => 'new', 'score' => 4],
            ],
        ])->assertRedirect(route('admin.questions.index'));

        $this->assertDatabaseHas('question_options', ['id' => $used->id, 'label' => 'Updated option', 'score' => 3]);
        $this->assertDatabaseHas('question_options', ['id' => $removed->id, 'is_active' => false]);
        $this->assertDatabaseHas('stress_responses', ['question_option_id' => $removed->id]);
        $this->assertDatabaseHas('question_options', ['stress_question_id' => $question->id, 'value' => 'new']);
    }

    public function test_scale_band_validation_rejects_invalid_and_overlapping_ranges(): void
    {
        $validator = app(ScaleBandValidator::class);

        try {
            $validator->validateCollection([['min_score' => 5, 'max_score' => 4, 'is_active' => true]]);
            $this->fail('Invalid range was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(ValidationException::class);
        $validator->validateCollection([
            ['min_score' => 0, 'max_score' => 5, 'is_active' => true],
            ['min_score' => 5, 'max_score' => 10, 'is_active' => true],
        ]);
    }

    public function test_scoring_uses_database_scores_and_finds_questionnaire_band(): void
    {
        $questionnaire = Questionnaire::query()->create(['title' => 'Score me', 'type' => 'stress', 'version' => 1]);
        $answers = [];
        foreach ([2, 3, 1] as $position => $score) {
            [$question, $option] = $this->questionWithOptions("Question {$position}", $score);
            $questionnaire->questions()->attach($question, ['position' => $position + 1, 'is_required' => true]);
            $answers[$question->id] = $option->id;
        }
        $band = $questionnaire->scoreBands()->create(['code' => 'moderate', 'label' => 'Moderate', 'min_score' => 5, 'max_score' => 8, 'position' => 1, 'is_active' => true]);

        $result = app(AssessmentScoringService::class)->score($questionnaire, $answers);
        $this->assertSame(6, $result['total_score']);
        $this->assertTrue($band->is($result['score_band']));
    }

    #[DataProvider('invalidScoringCases')]
    public function test_scoring_fails_safely(string $case): void
    {
        $questionnaire = Questionnaire::query()->create(['title' => $case, 'type' => 'stress', 'version' => 1]);
        [$question, $option] = $this->questionWithOptions();
        $questionnaire->questions()->attach($question, ['position' => 1, 'is_required' => true]);
        $questionnaire->scoreBands()->create(['code' => 'only', 'label' => 'Only', 'min_score' => 1, 'max_score' => 1, 'is_active' => true]);

        if ($case === 'inactive') {
            $option->update(['is_active' => false]);
        }
        if ($case === 'no_band') {
            $option->update(['score' => 10]);
        }
        $answers = match ($case) {
            'missing' => [],
            'inactive', 'no_band' => [$question->id => $option->id],
            'wrong_question' => [StressQuestion::query()->create(['question_text' => 'Other'])->id => $option->id],
        };

        $this->expectException(ValidationException::class);
        app(AssessmentScoringService::class)->score($questionnaire, $answers);
    }

    public static function invalidScoringCases(): array
    {
        return [['missing'], ['inactive'], ['wrong_question'], ['no_band']];
    }

    private function questionWithOptions(string $text = 'Question', int $firstScore = 1): array
    {
        $question = StressQuestion::query()->create(['question_text' => $text, 'question_type' => 'scale', 'is_active' => true]);
        $first = $question->options()->create(['label' => 'First', 'value' => 'first', 'score' => $firstScore, 'position' => 1, 'is_active' => true]);
        $second = $question->options()->create(['label' => 'Second', 'value' => 'second', 'score' => 2, 'position' => 2, 'is_active' => false]);

        return [$question, $first, $second];
    }
}
