<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionOption;
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

    public function test_admin_can_create_questionnaire_with_ordered_questions_and_bands(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$first] = $this->questionWithOptions('First');
        [$second] = $this->questionWithOptions('Second');

        $this->actingAs($admin)->post('/admin/questionnaires', [
            'title' => 'Stress check', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => '1',
            'questions' => [
                ['id' => $second->id, 'position' => 1, 'is_required' => '1'],
                ['id' => $first->id, 'position' => 2, 'is_required' => '1'],
            ],
            'bands' => [
                ['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 4, 'position' => 1, 'is_active' => '1'],
                ['code' => 'high', 'label' => 'High', 'min_score' => 5, 'max_score' => 10, 'position' => 2, 'is_active' => '1'],
            ],
        ])->assertRedirect(route('admin.questionnaires.index'));

        $questionnaire = Questionnaire::query()->firstOrFail();
        $this->assertSame([$second->id, $first->id], $questionnaire->questions()->orderBy('questionnaire_questions.position')->pluck('stress_questions.id')->all());
        $this->assertCount(2, $questionnaire->scoreBands);
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
