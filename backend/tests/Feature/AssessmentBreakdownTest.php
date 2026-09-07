<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_a_sectioned_questionnaire_persists_category_results_and_a_config_snapshot(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        [$questionnaire, $answers] = $this->sectionedQuestionnaire();

        $response = $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => $answers,
        ])->assertCreated();

        // Legacy shape preserved.
        $response->assertJsonPath('result.band.label', 'High mental well-being')
            ->assertJsonMissingPath('result.band.min_score');

        // New breakdown present.
        $response->assertJsonCount(2, 'result.breakdown.categories')
            ->assertJsonPath('result.breakdown.overall.max_weighted_score', 10)
            ->assertJsonPath('result.breakdown.stress', null);

        $assessment = StressAssessment::query()->firstOrFail();
        $this->assertSame(2, $assessment->categoryResults()->count());
        $this->assertNotNull($assessment->overall_weighted_score);
        $this->assertNotNull($assessment->wellbeing_result_band_id);
        $this->assertIsArray($assessment->config_snapshot);
        $this->assertCount(4, $assessment->config_snapshot['questions']);

        // Every response carries the adjusted scored value.
        $this->assertSame(4, $assessment->responses()->whereNotNull('scored_value')->count());
    }

    public function test_a_historical_result_is_not_recomputed_when_configuration_changes_later(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        [$questionnaire, $answers, $sections] = $this->sectionedQuestionnaire(returnSections: true);

        $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => $answers,
        ])->assertCreated();

        $assessment = StressAssessment::query()->firstOrFail();
        $originalWeighted = (float) $assessment->overall_weighted_score;
        $originalSnapshot = $assessment->config_snapshot;

        // An admin later doubles a category weight and flips reverse scoring.
        $sections->first()->update(['category_weight' => 999]);
        $sections->first()->questions->each->update(['is_reverse_scored' => true]);

        $assessment->refresh();
        $this->assertSame($originalWeighted, (float) $assessment->overall_weighted_score);
        $this->assertSame($originalSnapshot, $assessment->config_snapshot);
        $this->assertSame(5.0, (float) $assessment->config_snapshot['sections'][0]['category_weight']);
    }

    /**
     * Two sections, weight 5 each. All answers at max -> 100% each ->
     * weighted 10/10 -> "High mental well-being".
     *
     * @return array{0: Questionnaire, 1: list<array{question_id:int, option_id:int}>, 2?: Collection}
     */
    private function sectionedQuestionnaire(bool $returnSections = false): array
    {
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Wellbeing', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);

        $answers = [];
        $position = 0;
        $sections = collect();

        foreach (['Alpha', 'Beta'] as $index => $title) {
            $section = QuestionnaireSection::query()->create([
                'questionnaire_id' => $questionnaire->id,
                'title' => $title, 'position' => $index + 1, 'category_weight' => 5, 'is_active' => true,
            ]);

            for ($i = 0; $i < 2; $i++) {
                $position++;
                $question = StressQuestion::query()->create([
                    'question_text' => "Q{$position}", 'question_type' => 'scale',
                    'min_score' => 1, 'max_score' => 5, 'is_active' => true,
                ]);
                $chosen = null;
                for ($s = 1; $s <= 5; $s++) {
                    $option = $question->options()->create([
                        'label' => "S{$s}", 'value' => "q{$position}s{$s}", 'score' => $s, 'position' => $s, 'is_active' => true,
                    ]);
                    if ($s === 5) {
                        $chosen = $option;
                    }
                }
                $questionnaire->questions()->attach($question->id, [
                    'questionnaire_section_id' => $section->id, 'position' => $position, 'is_required' => true,
                ]);
                $answers[] = ['question_id' => $question->id, 'option_id' => $chosen->id];
            }

            $section->load('questions.options');
            $sections->push($section);
        }

        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low mental well-being', 'min_score' => 0, 'max_score' => 6, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 'high', 'label' => 'High mental well-being', 'min_score' => 7, 'max_score' => 10, 'scope' => 'overall', 'is_active' => true]);

        return $returnSections ? [$questionnaire, $answers, $sections] : [$questionnaire, $answers];
    }
}
