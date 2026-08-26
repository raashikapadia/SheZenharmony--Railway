<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionOption;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_student_can_submit_and_receive_a_safe_persisted_result(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);
        $questionnaire = $this->questionnaire();
        [$firstQuestion, $firstOption] = $this->question($questionnaire, 1, 2, 'How tense?', 'Often');
        [$secondQuestion, $secondOption] = $this->question($questionnaire, 2, 3, 'How worried?', 'Sometimes');
        [$thirdQuestion, $thirdOption] = $this->question($questionnaire, 3, 1, 'How rested?', 'Rarely');
        $band = $questionnaire->scoreBands()->create([
            'code' => 'moderate', 'label' => 'Moderate', 'min_score' => 5, 'max_score' => 8,
            'position' => 1, 'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => [
                ['question_id' => $firstQuestion->id, 'option_id' => $firstOption->id],
                ['question_id' => $secondQuestion->id, 'option_id' => $secondOption->id],
                ['question_id' => $thirdQuestion->id, 'option_id' => $thirdOption->id],
            ],
        ])->assertCreated()
            ->assertJsonPath('assessment.questionnaire_id', $questionnaire->id)
            ->assertJsonPath('result.total_score', 6)
            ->assertJsonPath('result.band.code', 'moderate')
            ->assertJsonPath('result.band.label', 'Moderate')
            ->assertJsonMissingPath('result.band.min_score')
            ->assertJsonMissingPath('result.band.max_score');

        $assessmentId = $response->json('assessment.id');
        $this->assertDatabaseCount('stress_assessments', 1);
        $this->assertDatabaseCount('stress_responses', 3);
        $this->assertDatabaseHas('stress_assessments', [
            'id' => $assessmentId, 'user_id' => null,
            'student_identity_id' => $student->studentIdentity->id,
            'questionnaire_id' => $questionnaire->id,
            'total_score' => 6, 'stress_score_band_id' => $band->id,
            'assessment_status' => 'completed', 'stress_level' => 'Moderate',
        ]);
        $this->assertNotNull($response->json('assessment.completed_at'));
        $this->assertDatabaseHas('stress_responses', [
            'stress_assessment_id' => $assessmentId, 'stress_question_id' => $firstQuestion->id,
            'question_option_id' => $firstOption->id, 'score' => 2,
            'question_text_snapshot' => 'How tense?', 'option_text_snapshot' => 'Often',
        ]);
    }

    public function test_submission_requires_sanctum_authentication(): void
    {
        $this->postJson('/api/v1/assessments', [])->assertUnauthorized();
    }

    public function test_missing_required_answer_is_rejected_without_partial_data(): void
    {
        [$questionnaire] = $this->authenticatedQuestionnaire();
        [$requiredQuestion] = $this->question($questionnaire, 1, 1);
        [$answeredQuestion, $answeredOption] = $this->question($questionnaire, 2, 1);
        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5, 'is_active' => true]);

        $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => [['question_id' => $answeredQuestion->id, 'option_id' => $answeredOption->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('answers');

        $this->assertNotNull($requiredQuestion->id);
        $this->assertDatabaseCount('stress_assessments', 0);
        $this->assertDatabaseCount('stress_responses', 0);
    }

    public function test_question_from_another_questionnaire_is_rejected(): void
    {
        [$questionnaire] = $this->authenticatedQuestionnaire();
        $other = Questionnaire::query()->create(['title' => 'Other', 'type' => 'stress', 'version' => 2, 'status' => 'published', 'is_active' => true]);
        [$question, $option] = $this->question($other, 1, 1);

        $this->assertRejected($questionnaire, [['question_id' => $question->id, 'option_id' => $option->id]]);
    }

    public function test_cross_question_option_is_rejected(): void
    {
        [$questionnaire] = $this->authenticatedQuestionnaire();
        [$question] = $this->question($questionnaire, 1, 1);
        [$otherQuestion, $otherOption] = $this->question($questionnaire, 2, 1);
        $questionnaire->questions()->updateExistingPivot($otherQuestion->id, ['is_required' => false]);
        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5, 'is_active' => true]);

        $this->assertRejected($questionnaire, [['question_id' => $question->id, 'option_id' => $otherOption->id]]);
    }

    public function test_inactive_option_and_inactive_question_are_rejected(): void
    {
        [$questionnaire] = $this->authenticatedQuestionnaire();
        [$question, $option] = $this->question($questionnaire, 1, 1);
        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5, 'is_active' => true]);

        $option->update(['is_active' => false]);
        $this->assertRejected($questionnaire, [['question_id' => $question->id, 'option_id' => $option->id]]);

        $option->update(['is_active' => true]);
        $question->update(['is_active' => false]);
        $this->assertRejected($questionnaire, [['question_id' => $question->id, 'option_id' => $option->id]]);
    }

    public function test_unscored_option_is_rejected(): void
    {
        [$questionnaire] = $this->authenticatedQuestionnaire();
        [$question, $option] = $this->question($questionnaire, 1, 1);
        $option->update(['score' => null]);
        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5, 'is_active' => true]);

        $this->assertRejected($questionnaire, [['question_id' => $question->id, 'option_id' => $option->id]]);
    }

    #[DataProvider('unavailableQuestionnaireStates')]
    public function test_unavailable_questionnaire_is_rejected(string $status, bool $active): void
    {
        [, $student] = $this->authenticatedQuestionnaire();
        $questionnaire = Questionnaire::query()->create([
            'title' => "Unavailable {$status}", 'type' => 'stress', 'version' => 2,
            'status' => $status, 'is_active' => $active,
        ]);
        [$question, $option] = $this->question($questionnaire, 1, 1);

        $this->assertRejected($questionnaire, [['question_id' => $question->id, 'option_id' => $option->id]]);
        $this->assertTrue($student->isStudent());
    }

    public static function unavailableQuestionnaireStates(): array
    {
        return [['draft', true], ['archived', false], ['published', false]];
    }

    public function test_duplicate_question_answers_are_rejected(): void
    {
        [$questionnaire] = $this->authenticatedQuestionnaire();
        [$question, $option] = $this->question($questionnaire, 1, 1);

        $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => [
                ['question_id' => $question->id, 'option_id' => $option->id],
                ['question_id' => $question->id, 'option_id' => $option->id],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('answers.0.question_id');

        $this->assertDatabaseCount('stress_assessments', 0);
    }

    public function test_client_scoring_fields_are_rejected_and_cannot_control_results(): void
    {
        [$questionnaire] = $this->authenticatedQuestionnaire();
        [$question, $option] = $this->question($questionnaire, 1, 4);
        $questionnaire->scoreBands()->create(['code' => 'configured', 'label' => 'Configured', 'min_score' => 4, 'max_score' => 4, 'is_active' => true]);

        $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'total_score' => 999,
            'stress_score_band_id' => 999,
            'answers' => [['question_id' => $question->id, 'option_id' => $option->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors(['total_score', 'stress_score_band_id']);

        $this->assertDatabaseCount('stress_assessments', 0);
    }

    public function test_no_matching_band_rolls_back_without_assessment_or_responses(): void
    {
        [$questionnaire] = $this->authenticatedQuestionnaire();
        [$question, $option] = $this->question($questionnaire, 1, 10);
        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5, 'is_active' => true]);

        $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => [['question_id' => $question->id, 'option_id' => $option->id]],
        ])->assertUnprocessable()->assertJsonValidationErrors('score_bands');

        $this->assertDatabaseCount('stress_assessments', 0);
        $this->assertDatabaseCount('stress_responses', 0);
    }

    private function questionnaire(): Questionnaire
    {
        return Questionnaire::query()->create([
            'title' => 'Student assessment', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);
    }

    private function authenticatedQuestionnaire(): array
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        return [$this->questionnaire(), $student];
    }

    private function question(Questionnaire $questionnaire, int $position, int $score, string $text = 'Question', string $label = 'Answer'): array
    {
        $question = StressQuestion::query()->create(['question_text' => $text, 'question_type' => 'scale', 'is_active' => true]);
        $option = QuestionOption::query()->create([
            'stress_question_id' => $question->id, 'label' => $label, 'value' => "value-{$position}",
            'score' => $score, 'position' => 1, 'is_active' => true,
        ]);
        $questionnaire->questions()->attach($question, ['position' => $position, 'is_required' => true]);

        return [$question, $option];
    }

    private function assertRejected(Questionnaire $questionnaire, array $answers): void
    {
        $this->postJson('/api/v1/assessments', ['questionnaire_id' => $questionnaire->id, 'answers' => $answers])
            ->assertUnprocessable();
        $this->assertDatabaseCount('stress_assessments', 0);
        $this->assertDatabaseCount('stress_responses', 0);
    }
}
