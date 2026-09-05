<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionOption;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_their_own_completed_assessment_detail(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        $assessmentId = $this->submitAssessment();

        $this->getJson("/api/v1/assessments/{$assessmentId}")
            ->assertOk()
            ->assertJsonPath('data.id', $assessmentId)
            ->assertJsonPath('data.total_score', 4)
            ->assertJsonPath('data.score_out_of', 10)
            ->assertJsonPath('data.band.code', 'low')
            ->assertJsonPath('data.responses.0.question', 'How stressed?')
            ->assertJsonPath('data.responses.0.answer', 'Sometimes')
            ->assertJsonPath('data.responses.0.score', 4);
    }

    public function test_a_student_cannot_read_another_students_assessment(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($owner, ['student']);
        $assessmentId = $this->submitAssessment();

        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($other, ['student']);

        $this->getJson("/api/v1/assessments/{$assessmentId}")->assertNotFound();
        $this->getJson('/api/v1/assessments/999999')->assertNotFound();
    }

    public function test_detail_requires_authentication(): void
    {
        $this->getJson('/api/v1/assessments/1')->assertUnauthorized();
    }

    private function submitAssessment(): int
    {
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Stress', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);
        $question = StressQuestion::query()->create([
            'question_text' => 'How stressed?', 'question_type' => 'scale', 'is_active' => true,
        ]);
        $option = QuestionOption::query()->create([
            'stress_question_id' => $question->id, 'label' => 'Sometimes', 'value' => 'sometimes',
            'score' => 4, 'position' => 1, 'is_active' => true,
        ]);
        $questionnaire->questions()->attach($question, ['position' => 1, 'is_required' => true]);
        $questionnaire->scoreBands()->create([
            'code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5,
            'position' => 1, 'is_active' => true,
        ]);
        $questionnaire->scoreBands()->create([
            'code' => 'high', 'label' => 'High', 'min_score' => 6, 'max_score' => 10,
            'position' => 2, 'is_active' => true,
        ]);

        return (int) $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => [['question_id' => $question->id, 'option_id' => $option->id]],
        ])->assertCreated()->json('assessment.id');
    }
}
