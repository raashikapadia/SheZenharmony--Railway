<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Services\AssessmentScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WellbeingScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_section_less_questionnaire_still_scores_flat_with_no_breakdown(): void
    {
        $questionnaire = Questionnaire::query()->create(['title' => 'Flat', 'type' => 'stress', 'version' => 1]);
        $answers = [];
        foreach ([2, 3, 1] as $i => $score) {
            [$question, $optionId] = $this->scaleQuestion($questionnaire, position: $i + 1, chosenScore: $score);
            $answers[$question->id] = $optionId;
        }
        $questionnaire->scoreBands()->create([
            'code' => 'mid', 'label' => 'Mid', 'min_score' => 5, 'max_score' => 8, 'scope' => 'overall', 'is_active' => true,
        ]);

        $scored = app(AssessmentScoringService::class)->score($questionnaire, $answers);

        $this->assertSame(6, $scored['total_score']);
        $this->assertNull($scored['breakdown']);
        $this->assertNull($scored['config_snapshot']);
    }

    public function test_each_category_scores_as_a_share_of_its_points_then_applies_its_weight(): void
    {
        [$questionnaire, $sections] = $this->sectionedQuestionnaire([
            ['weight' => 6, 'questions' => 2],
            ['weight' => 4, 'questions' => 2],
        ]);

        // Section A: both answered at max (10 / 10) -> 100% -> 6.0 weighted.
        // Section B: both answered at min (2 / 10)  -> 20%  -> 0.8 weighted.
        $answers = [];
        foreach ($sections[0]->questions as $q) {
            $answers[$q->id] = $q->options->firstWhere('score', 5)->id;
        }
        foreach ($sections[1]->questions as $q) {
            $answers[$q->id] = $q->options->firstWhere('score', 1)->id;
        }

        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 5, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 'high', 'label' => 'High', 'min_score' => 6, 'max_score' => 10, 'scope' => 'overall', 'is_active' => true]);

        $scored = app(AssessmentScoringService::class)->score($questionnaire, $answers);

        $this->assertEqualsWithDelta(6.8, $scored['breakdown']['overall']['weighted_score'], 0.001);
        $this->assertEqualsWithDelta(10.0, $scored['breakdown']['overall']['max_weighted_score'], 0.001);
        $this->assertEqualsWithDelta(68.0, $scored['breakdown']['overall']['percentage'], 0.001);
        $this->assertSame('High', $scored['score_band']->label);          // round(6.8) = 7
        $this->assertSame(100.0, (float) $scored['breakdown']['categories'][0]['percentage']);
        $this->assertSame(20.0, (float) $scored['breakdown']['categories'][1]['percentage']);
    }

    public function test_all_neutral_answers_land_mid_range_not_at_the_floor(): void
    {
        [$questionnaire, $sections] = $this->sectionedQuestionnaire([
            ['weight' => 5, 'questions' => 4],
        ]);
        // Every answer "3" on a 1..5 scale -> 12 / 20 -> 60% -> 3.0 weighted.
        $answers = [];
        foreach ($sections[0]->questions as $q) {
            $answers[$q->id] = $q->options->firstWhere('score', 3)->id;
        }
        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 2, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 'mod', 'label' => 'Moderate', 'min_score' => 3, 'max_score' => 5, 'scope' => 'overall', 'is_active' => true]);

        $scored = app(AssessmentScoringService::class)->score($questionnaire, $answers);

        $this->assertEqualsWithDelta(3.0, $scored['breakdown']['overall']['weighted_score'], 0.001);
        $this->assertSame('Moderate', $scored['score_band']->label);
    }

    public function test_reverse_scoring_flips_the_value_within_the_configured_range(): void
    {
        [$questionnaire, $sections] = $this->sectionedQuestionnaire([
            ['weight' => 10, 'questions' => 1, 'reverse' => true],
        ]);
        $question = $sections[0]->questions->first();
        // Answer 5 on a reverse-scored 1..5 question -> effective 1 -> 1 / 5 -> 20%.
        $answers = [$question->id => $question->options->firstWhere('score', 5)->id];

        $questionnaire->scoreBands()->create(['code' => 'z', 'label' => 'Zero', 'min_score' => 0, 'max_score' => 3, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 't', 'label' => 'Ten', 'min_score' => 4, 'max_score' => 10, 'scope' => 'overall', 'is_active' => true]);

        $scored = app(AssessmentScoringService::class)->score($questionnaire, $answers);

        $this->assertSame(20.0, (float) $scored['breakdown']['categories'][0]['percentage']);
        $this->assertSame('Zero', $scored['score_band']->label);          // round(2.0) = 2
    }

    public function test_reverse_scoring_is_range_aware_for_a_one_to_ten_question(): void
    {
        [$questionnaire, $sections] = $this->sectionedQuestionnaire([
            ['weight' => 10, 'questions' => 1, 'reverse' => true, 'scale' => 10],
        ]);
        $question = $sections[0]->questions->first();
        // reverse(10) on a 1..10 question = 1+10-10 = 1 -> 1 / 10 -> 10%.
        $answers = [$question->id => $question->options->firstWhere('score', 10)->id];

        $questionnaire->scoreBands()->create(['code' => 'z', 'label' => 'Zero', 'min_score' => 0, 'max_score' => 5, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 't', 'label' => 'Ten', 'min_score' => 6, 'max_score' => 10, 'scope' => 'overall', 'is_active' => true]);

        $scored = app(AssessmentScoringService::class)->score($questionnaire, $answers);

        $this->assertSame(10.0, (float) $scored['breakdown']['categories'][0]['percentage']);
    }

    public function test_stress_sub_score_normalises_to_0_100_and_respects_direction(): void
    {
        [$questionnaire, $sections] = $this->sectionedQuestionnaire([
            ['weight' => 5, 'questions' => 2, 'stress' => true],
        ]);
        $questions = $sections[0]->questions->values();
        // Q1 higher_more_stress, answered 5/5 -> fraction 1.
        // Q2 higher_less_stress, answered 5/5 -> fraction 0.
        $questions[0]->update(['stress_direction' => StressQuestion::STRESS_DIRECTION_MORE]);
        $questions[1]->update(['stress_direction' => StressQuestion::STRESS_DIRECTION_LESS]);
        $answers = [
            $questions[0]->id => $questions[0]->options->firstWhere('score', 5)->id,
            $questions[1]->id => $questions[1]->options->firstWhere('score', 5)->id,
        ];

        $questionnaire->scoreBands()->create(['code' => 'ow', 'label' => 'OK', 'min_score' => 0, 'max_score' => 5, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 's-lo', 'label' => 'Low stress', 'min_score' => 0, 'max_score' => 49, 'scope' => 'stress', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 's-mid', 'label' => 'Some stress', 'min_score' => 50, 'max_score' => 100, 'scope' => 'stress', 'is_active' => true]);

        $scored = app(AssessmentScoringService::class)->score($questionnaire, $answers);

        $this->assertEqualsWithDelta(50.0, $scored['breakdown']['stress']['score'], 0.001);
        $this->assertSame('Some stress', $scored['breakdown']['stress']['band']['label']);
    }

    public function test_missing_wellbeing_band_for_the_weighted_score_fails_safely(): void
    {
        [$questionnaire, $sections] = $this->sectionedQuestionnaire([
            ['weight' => 5, 'questions' => 1],
        ]);
        $question = $sections[0]->questions->first();
        $answers = [$question->id => $question->options->firstWhere('score', 5)->id];
        // Band only covers 0..1, weighted score will be 5.
        $questionnaire->scoreBands()->create(['code' => 'x', 'label' => 'X', 'min_score' => 0, 'max_score' => 1, 'scope' => 'overall', 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(AssessmentScoringService::class)->score($questionnaire, $answers);
    }

    /**
     * @param  array<int, array{weight: int, questions: int, reverse?: bool, stress?: bool, scale?: int}>  $spec
     * @return array{0: Questionnaire, 1: Collection<int, QuestionnaireSection>}
     */
    private function sectionedQuestionnaire(array $spec): array
    {
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $position = 0;
        $sections = collect();

        foreach ($spec as $sectionIndex => $sectionSpec) {
            $section = QuestionnaireSection::query()->create([
                'questionnaire_id' => $questionnaire->id,
                'title' => 'Section '.($sectionIndex + 1),
                'position' => $sectionIndex + 1,
                'category_weight' => $sectionSpec['weight'],
                'is_active' => true,
            ]);

            for ($i = 0; $i < $sectionSpec['questions']; $i++) {
                $position++;
                $scale = $sectionSpec['scale'] ?? 5;
                $question = StressQuestion::query()->create([
                    'question_text' => "Q{$position}",
                    'question_type' => 'scale',
                    'min_score' => 1,
                    'max_score' => $scale,
                    'is_reverse_scored' => $sectionSpec['reverse'] ?? false,
                    'stress_relevant' => $sectionSpec['stress'] ?? false,
                    'stress_direction' => ($sectionSpec['stress'] ?? false) ? StressQuestion::STRESS_DIRECTION_MORE : null,
                    'is_active' => true,
                ]);
                for ($s = 1; $s <= $scale; $s++) {
                    $question->options()->create([
                        'label' => "Score {$s}", 'value' => "s{$s}", 'score' => $s, 'position' => $s, 'is_active' => true,
                    ]);
                }
                $questionnaire->questions()->attach($question->id, [
                    'questionnaire_section_id' => $section->id,
                    'position' => $position,
                    'is_required' => true,
                ]);
            }

            $section->load('questions.options');
            $sections->push($section);
        }

        return [$questionnaire, $sections];
    }

    /** @return array{0: StressQuestion, 1: int} chosen option id */
    private function scaleQuestion(Questionnaire $questionnaire, int $position, int $chosenScore): array
    {
        $question = StressQuestion::query()->create([
            'question_text' => "Flat Q{$position}", 'question_type' => 'scale', 'is_active' => true,
        ]);
        $chosen = null;
        foreach ([1, 2, 3] as $score) {
            $option = $question->options()->create([
                'label' => "S{$score}", 'value' => "v{$position}-{$score}", 'score' => $score, 'position' => $score, 'is_active' => true,
            ]);
            if ($score === $chosenScore) {
                $chosen = $option;
            }
        }
        $questionnaire->questions()->attach($question->id, ['position' => $position, 'is_required' => true]);

        return [$question, $chosen->id];
    }
}
