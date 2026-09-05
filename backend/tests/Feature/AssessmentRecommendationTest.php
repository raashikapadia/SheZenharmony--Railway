<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Questionnaire;
use App\Models\QuestionOption;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_result_returns_published_interventions_for_the_computed_band_and_all_levels_only(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_STUDENT]), ['student']);

        $questionnaire = Questionnaire::query()->create([
            'title' => 'Stress', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);
        [$question, $option] = $this->question($questionnaire, 6);
        $low = $questionnaire->scoreBands()->create([
            'code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5,
            'position' => 1, 'is_active' => true,
        ]);
        $moderate = $questionnaire->scoreBands()->create([
            'code' => 'moderate', 'label' => 'Moderate', 'min_score' => 6, 'max_score' => 10,
            'position' => 2, 'is_active' => true,
        ]);

        $matched = $this->intervention('Breathing for stress', true);
        $matched->recommendations()->create(['stress_score_band_id' => $moderate->id, 'is_active' => true, 'priority' => 1]);

        $allLevels = $this->intervention('Always available resource', true);

        $wrongBand = $this->intervention('Only for low', true);
        $wrongBand->recommendations()->create(['stress_score_band_id' => $low->id, 'is_active' => true, 'priority' => 0]);

        $draft = $this->intervention('Unpublished helper', false);
        $draft->recommendations()->create(['stress_score_band_id' => $moderate->id, 'is_active' => true, 'priority' => 0]);

        $response = $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => [['question_id' => $question->id, 'option_id' => $option->id]],
        ])->assertCreated()
            ->assertJsonPath('result.total_score', 6)
            ->assertJsonPath('result.score_out_of', 10)
            ->assertJsonPath('result.band.code', 'moderate');

        $titles = collect($response->json('recommended_interventions'))->pluck('title')->all();
        sort($titles);

        $this->assertSame(['Always available resource', 'Breathing for stress'], $titles);
        $this->assertNotContains('Only for low', $titles);
        $this->assertNotContains('Unpublished helper', $titles);
    }

    public function test_recommended_intervention_payload_excludes_internal_fields(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_STUDENT]), ['student']);

        $questionnaire = Questionnaire::query()->create([
            'title' => 'Stress', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);
        [$question, $option] = $this->question($questionnaire, 3);
        $questionnaire->scoreBands()->create([
            'code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5,
            'position' => 1, 'is_active' => true,
        ]);
        $this->intervention('Grounding', true);

        $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => [['question_id' => $question->id, 'option_id' => $option->id]],
        ])->assertCreated()
            ->assertJsonPath('recommended_interventions.0.title', 'Grounding')
            ->assertJsonMissingPath('recommended_interventions.0.id')
            ->assertJsonMissingPath('recommended_interventions.0.is_active');
    }

    private function question(Questionnaire $questionnaire, int $score): array
    {
        $question = StressQuestion::query()->create([
            'question_text' => 'How are you?', 'question_type' => 'scale', 'is_active' => true,
        ]);
        $option = QuestionOption::query()->create([
            'stress_question_id' => $question->id, 'label' => 'A lot', 'value' => 'a-lot',
            'score' => $score, 'position' => 1, 'is_active' => true,
        ]);
        $questionnaire->questions()->attach($question, ['position' => 1, 'is_required' => true]);

        return [$question, $option];
    }

    private function intervention(string $title, bool $published): Intervention
    {
        return Intervention::query()->create([
            'title' => $title,
            'description' => 'Support content.',
            'content_type' => 'resource',
            'instructions' => 'Try this.',
            'is_active' => $published,
        ]);
    }
}
