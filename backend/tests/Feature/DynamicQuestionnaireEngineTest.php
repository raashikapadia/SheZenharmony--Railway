<?php

namespace Tests\Feature;

use App\Enums\AppScreen;
use App\Models\Intervention;
use App\Models\InterventionRecommendation;
use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\User;
use App\Services\AssessmentScoringService;
use App\Services\QuestionnaireActivationService;
use App\Services\QuestionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The scoring engine is configuration-driven: these scenarios build very
 * different questionnaires through the same code and check that every
 * total, percentage and level comes out of the stored configuration alone.
 *
 * TEST 1 simple · TEST 2 mixed question types · TEST 3 reversed questions ·
 * TEST 4 custom section weights · TEST 5 a 1–10 scale · TEST 6 changing the
 * question count · TEST 7 many result levels · TEST 8 history survives
 * configuration changes · plus registration/library separation.
 */
class DynamicQuestionnaireEngineTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function student(): User
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        return $student;
    }

    /**
     * A published questionnaire on the recommended configuration: weighted
     * sections, equal weights, results as a percentage.
     */
    private function questionnaire(array $overrides = []): Questionnaire
    {
        return Questionnaire::query()->create(array_merge([
            'title' => 'Dynamic check', 'type' => 'dynamic_check', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
            'result_scale_min' => 0, 'result_scale_max' => 100,
            'scoring_method' => Questionnaire::SCORING_WEIGHTED_SECTIONS,
            'section_weighting' => Questionnaire::WEIGHTING_EQUAL,
        ], $overrides));
    }

    private function section(Questionnaire $questionnaire, string $title, float $weight = 1): QuestionnaireSection
    {
        return QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => $title,
            'position' => (int) $questionnaire->sections()->max('position') + 1,
            'category_weight' => $weight, 'is_active' => true,
        ]);
    }

    /**
     * One question written exactly as the admin form posts it, attached to a
     * section. `$points` are the option scores in order.
     *
     * @param  array<int, int>  $points
     * @param  array<string, mixed>  $config  answer_mode, scoring_method, max_selections, is_reverse_scored, wellbeing_weight …
     */
    private function question(Questionnaire $questionnaire, QuestionnaireSection $section, string $text, array $points, string $type = 'scale', array $config = [], bool $required = true): StressQuestion
    {
        $question = new StressQuestion(['dimension' => $section->title]);
        app(QuestionWriter::class)->save($question, array_merge([
            'question_text' => $text,
            'question_type' => $type,
            'is_active' => true,
            'options' => collect($points)->map(fn ($p, $i) => ['label' => "Answer {$p}", 'value' => "a{$i}_{$p}", 'score' => $p])->values()->all(),
        ], $config));
        $questionnaire->questions()->attach($question->id, [
            'questionnaire_section_id' => $section->id,
            'position' => (int) $questionnaire->questions()->max('questionnaire_questions.position') + 1,
            'is_required' => $required,
        ]);

        return $question->load('options');
    }

    /** @param  array<int, array{0: int, 1: int, 2: string}>  $levels  [min, max, label] */
    private function levels(Questionnaire $questionnaire, array $levels): void
    {
        foreach ($levels as $i => [$min, $max, $label]) {
            $questionnaire->scoreBands()->create([
                'code' => strtolower(str_replace(' ', '-', $label)), 'label' => $label,
                'min_score' => $min, 'max_score' => $max, 'position' => $i + 1,
                'scope' => 'overall', 'is_active' => true,
                'harmony_message' => "{$label} message",
            ]);
        }
    }

    /** The option of $question worth exactly $points. */
    private function worth(StressQuestion $question, int $points): int
    {
        return $question->options->firstWhere('score', $points)->id;
    }

    /** @param  array<int, array<string, mixed>>  $answers */
    private function submit(Questionnaire $questionnaire, array $answers)
    {
        return $this->postJson('/api/v1/assessments', ['questionnaire_id' => $questionnaire->id, 'answers' => $answers]);
    }

    // ------------------------------------------------------------------
    // TEST 1 — Simple: 2 sections × 3 questions, scale 1–5, equal weights, 3 levels
    // ------------------------------------------------------------------

    public function test_1_two_equal_sections_of_scale_questions_score_as_a_percentage_into_three_levels(): void
    {
        $this->student();
        $q = $this->questionnaire();
        $a = $this->section($q, 'Emotional Wellbeing');
        $b = $this->section($q, 'Academic Pressure');
        $qa = collect(range(1, 3))->map(fn ($i) => $this->question($q, $a, "Emotional {$i}", [1, 2, 3, 4, 5]));
        $qb = collect(range(1, 3))->map(fn ($i) => $this->question($q, $b, "Academic {$i}", [1, 2, 3, 4, 5]));
        $this->levels($q, [[0, 33, 'Low'], [34, 66, 'Moderate'], [67, 100, 'High']]);

        // Section A all 5s (15/15 = 100%), section B all 1s (3/15 = 20%).
        // Equal weights 50/50 → 50 + 10 = 60 of 100 → 60% → Moderate.
        $answers = $qa->map(fn ($x) => ['question_id' => $x->id, 'option_id' => $this->worth($x, 5)])
            ->merge($qb->map(fn ($x) => ['question_id' => $x->id, 'option_id' => $this->worth($x, 1)]))->all();

        $this->submit($q, $answers)
            ->assertCreated()
            ->assertJsonPath('result.total_score', 60)
            ->assertJsonPath('result.percentage', 60)
            ->assertJsonPath('result.band.label', 'Moderate')
            ->assertJsonPath('result.band.message', 'Moderate message')
            ->assertJsonPath('result.breakdown.method', 'weighted_sections')
            ->assertJsonPath('result.breakdown.categories.0.percentage', 100)
            ->assertJsonPath('result.breakdown.categories.0.category_weight', 50)
            ->assertJsonPath('result.breakdown.categories.1.percentage', 20)
            ->assertJsonPath('result.breakdown.overall.total_max', 100);

        // Everything at maximum is 100% → High; everything at minimum → 20% → Low.
        $max = $qa->merge($qb)->map(fn ($x) => ['question_id' => $x->id, 'option_id' => $this->worth($x, 5)])->all();
        $this->submit($q, $max)->assertCreated()->assertJsonPath('result.percentage', 100)->assertJsonPath('result.band.label', 'High');
        $min = $qa->merge($qb)->map(fn ($x) => ['question_id' => $x->id, 'option_id' => $this->worth($x, 1)])->all();
        $this->submit($q, $min)->assertCreated()->assertJsonPath('result.percentage', 20)->assertJsonPath('result.band.label', 'Low');

        // The admin's automatic figures agree with the engine.
        $overview = app(AssessmentScoringService::class)->overview($q->fresh());
        $this->assertSame(2, $overview['section_count']);
        $this->assertSame(6, $overview['question_count']);
        $this->assertSame([0, 100], $overview['total_span']);
        $this->assertSame([20.0, 100.0], $overview['percentage_range']);
        $this->assertSame(50.0, $overview['sections'][0]['weight']);
    }

    // ------------------------------------------------------------------
    // TEST 2 — Mixed question types in one questionnaire
    // ------------------------------------------------------------------

    public function test_2_scale_true_false_single_choice_and_multi_select_score_together(): void
    {
        $this->student();
        $q = $this->questionnaire(['scoring_method' => Questionnaire::SCORING_POINTS_TOTAL, 'section_weighting' => Questionnaire::WEIGHTING_CUSTOM]);
        $s = $this->section($q, 'Mixed', 1);

        $scale = $this->question($q, $s, 'Scale 1–5', [1, 2, 3, 4, 5]);
        // True/False: the configuration decides what each answer is worth — here False = 0, True = 3.
        $trueFalse = $this->question($q, $s, 'True or false', [0, 3], 'yes_no');
        // Single choice with its own points per answer (not in order).
        $single = $this->question($q, $s, 'How often?', [0, 4, 2, 6], 'multiple_choice');
        // Multi-select: ticked answers' points add up, up to 3 ticks.
        $multi = $this->question($q, $s, 'Which apply?', [2, 2, 3, 1], 'multiple_choice', [
            'answer_mode' => StressQuestion::ANSWER_MULTIPLE, 'scoring_method' => StressQuestion::SCORING_DIRECT, 'max_selections' => 3,
        ]);

        // Raw span: 1+0+0+0 = 1 up to 5+3+6+(3+2+2) = 21 → levels on 0–100 after normalising.
        $this->levels($q, [[0, 49, 'Lower half'], [50, 100, 'Upper half']]);

        $answers = [
            ['question_id' => $scale->id, 'option_id' => $this->worth($scale, 4)],
            ['question_id' => $trueFalse->id, 'option_id' => $this->worth($trueFalse, 3)],
            ['question_id' => $single->id, 'option_id' => $this->worth($single, 6)],
            ['question_id' => $multi->id, 'option_ids' => [$this->worth($multi, 3), $multi->options->firstWhere('score', 2)->id]],
        ];
        // Raw 4 + 3 + 6 + 5 = 18 of 1–21 → (18-1)/(21-1) = 85% → 85 on the 0–100 scale.
        $response = $this->submit($q, $answers)->assertCreated()
            ->assertJsonPath('result.total_score', 85)
            ->assertJsonPath('result.band.label', 'Upper half')
            ->assertJsonPath('result.breakdown.overall.raw_score', 18)
            ->assertJsonPath('result.breakdown.overall.raw_min', 1)
            ->assertJsonPath('result.breakdown.overall.raw_max', 21);

        // The multi-select transcript keeps every ticked option and their wording.
        $this->assertDatabaseHas('stress_responses', [
            'stress_assessment_id' => $response->json('assessment.id'),
            'stress_question_id' => $multi->id, 'question_option_id' => null, 'score' => 5,
            'option_text_snapshot' => 'Answer 2, Answer 3',
        ]);
        $this->assertSame(
            [$multi->options->firstWhere('score', 2)->id, $this->worth($multi, 3)],
            collect(StressAssessment::query()->findOrFail($response->json('assessment.id'))->responses()->where('stress_question_id', $multi->id)->value('selected_option_ids'))
                ->map(fn ($id) => (int) $id)->sort()->values()->all(),
        );
    }

    public function test_2b_multi_select_scoring_methods_and_limits_come_from_configuration(): void
    {
        $this->student();
        $q = $this->questionnaire(['scoring_method' => Questionnaire::SCORING_POINTS_TOTAL]);
        $s = $this->section($q, 'Symptoms');
        $count = $this->question($q, $s, 'Count ticks', [5, 5, 5, 5], 'multiple_choice', [
            'answer_mode' => StressQuestion::ANSWER_MULTIPLE, 'scoring_method' => StressQuestion::SCORING_COUNT_SELECTED,
        ]);
        $highest = $this->question($q, $s, 'Highest tick', [1, 4, 2, 9], 'multiple_choice', [
            'answer_mode' => StressQuestion::ANSWER_MULTIPLE, 'scoring_method' => StressQuestion::SCORING_MAX_SELECTED, 'max_selections' => 2,
        ]);
        // Raw span 0–13: count 0–4 (one point per tick), highest 0–9.
        $this->levels($q, [[0, 100, 'Any']]);

        $opts = fn (StressQuestion $x, array $scores) => array_map(fn ($sc) => $x->options->firstWhere('score', $sc)->id, $scores);

        $this->submit($q, [
            ['question_id' => $count->id, 'option_ids' => $count->options->take(3)->pluck('id')->all()],
            ['question_id' => $highest->id, 'option_ids' => $opts($highest, [4, 9])],
        ])->assertCreated()
            // 3 ticks = 3 points (the 5s are ignored); highest of 4 and 9 = 9 → raw 12 of 0–13 → 92.
            ->assertJsonPath('result.breakdown.overall.raw_score', 12)
            ->assertJsonPath('result.total_score', 92);

        // Ticking more than the question allows is refused server-side …
        $this->submit($q, [
            ['question_id' => $count->id, 'option_ids' => $count->options->take(1)->pluck('id')->all()],
            ['question_id' => $highest->id, 'option_ids' => $opts($highest, [1, 4, 2])],
        ])->assertUnprocessable()->assertJsonValidationErrors(['answers']);

        // … and so is a list of options for a single-answer question.
        $single = $this->question($q, $s, 'Pick one', [1, 2, 3], 'multiple_choice');
        $this->submit($q, [
            ['question_id' => $count->id, 'option_ids' => $count->options->take(1)->pluck('id')->all()],
            ['question_id' => $highest->id, 'option_ids' => $opts($highest, [1])],
            ['question_id' => $single->id, 'option_ids' => $single->options->take(2)->pluck('id')->all()],
        ])->assertUnprocessable()->assertJsonValidationErrors(['answers']);
    }

    // ------------------------------------------------------------------
    // TEST 3 — Reversed (positively worded) questions in the same section
    // ------------------------------------------------------------------

    public function test_3_reversed_questions_flip_within_their_own_range_alongside_normal_ones(): void
    {
        $this->student();
        $q = $this->questionnaire();
        $s = $this->section($q, 'Emotional');
        $negative = $this->question($q, $s, 'I feel overwhelmed', [1, 2, 3, 4, 5]);
        $positive = $this->question($q, $s, 'I feel calm most of the time', [1, 2, 3, 4, 5], 'scale', ['is_reverse_scored' => true]);
        $zeroBased = $this->question($q, $s, 'I sleep well', [0, 1, 2, 3, 4], 'scale', ['is_reverse_scored' => true]);
        $this->levels($q, [[0, 100, 'Any']]);

        // overwhelmed 5 → 5; calm 5 → 1; sleep 4 → 0. Raw 6 of section max 14 → 42.86%.
        $this->submit($q, [
            ['question_id' => $negative->id, 'option_id' => $this->worth($negative, 5)],
            ['question_id' => $positive->id, 'option_id' => $this->worth($positive, 5)],
            ['question_id' => $zeroBased->id, 'option_id' => $this->worth($zeroBased, 4)],
        ])->assertCreated()
            ->assertJsonPath('result.breakdown.categories.0.raw_score', 6)
            ->assertJsonPath('result.breakdown.categories.0.max_possible_score', 14)
            ->assertJsonPath('result.breakdown.categories.0.percentage', 42.86)
            ->assertJsonPath('result.total_score', 43);

        // Both extremes: answering the positive items at their minimum scores them at maximum.
        $this->submit($q, [
            ['question_id' => $negative->id, 'option_id' => $this->worth($negative, 5)],
            ['question_id' => $positive->id, 'option_id' => $this->worth($positive, 1)],
            ['question_id' => $zeroBased->id, 'option_id' => $this->worth($zeroBased, 0)],
        ])->assertCreated()->assertJsonPath('result.percentage', 100);
    }

    // ------------------------------------------------------------------
    // TEST 4 — Custom section weights 20 / 30 / 50
    // ------------------------------------------------------------------

    public function test_4_custom_section_weights_shape_the_total(): void
    {
        $this->student();
        $q = $this->questionnaire(['section_weighting' => Questionnaire::WEIGHTING_CUSTOM]);
        $a = $this->section($q, 'A', 20);
        $b = $this->section($q, 'B', 30);
        $c = $this->section($q, 'C', 50);
        $qa = $this->question($q, $a, 'A1', [0, 1, 2, 3, 4]);
        $qb = $this->question($q, $b, 'B1', [0, 1, 2, 3, 4]);
        $qc = $this->question($q, $c, 'C1', [0, 1, 2, 3, 4]);
        $this->levels($q, [[0, 100, 'Any']]);

        // A at 100% → 20, B at 50% → 15, C at 0% → 0 = 35 of 100.
        $this->submit($q, [
            ['question_id' => $qa->id, 'option_id' => $this->worth($qa, 4)],
            ['question_id' => $qb->id, 'option_id' => $this->worth($qb, 2)],
            ['question_id' => $qc->id, 'option_id' => $this->worth($qc, 0)],
        ])->assertCreated()
            ->assertJsonPath('result.total_score', 35)
            ->assertJsonPath('result.breakdown.categories.0.weighted_score', 20)
            ->assertJsonPath('result.breakdown.categories.1.weighted_score', 15)
            ->assertJsonPath('result.breakdown.categories.2.weighted_score', 0)
            ->assertJsonPath('result.breakdown.overall.total_max', 100);

        // The same answers with C at 100% instead: 20 + 15 + 50 = 85.
        $this->submit($q, [
            ['question_id' => $qa->id, 'option_id' => $this->worth($qa, 4)],
            ['question_id' => $qb->id, 'option_id' => $this->worth($qb, 2)],
            ['question_id' => $qc->id, 'option_id' => $this->worth($qc, 4)],
        ])->assertCreated()->assertJsonPath('result.total_score', 85);

        // Weights need not sum to 100: 1 / 1 / 2 gives the same shares.
        $q->sections()->whereKey($a->id)->update(['category_weight' => 1]);
        $q->sections()->whereKey($b->id)->update(['category_weight' => 1]);
        $q->sections()->whereKey($c->id)->update(['category_weight' => 2]);
        $this->submit($q, [
            ['question_id' => $qa->id, 'option_id' => $this->worth($qa, 4)],
            ['question_id' => $qb->id, 'option_id' => $this->worth($qb, 4)],
            ['question_id' => $qc->id, 'option_id' => $this->worth($qc, 0)],
        ])->assertCreated()->assertJsonPath('result.total_score', 50)->assertJsonPath('result.breakdown.overall.total_max', 4);
    }

    // ------------------------------------------------------------------
    // TEST 5 — A 1–10 scale adapts without any code change
    // ------------------------------------------------------------------

    public function test_5_a_one_to_ten_scale_is_scored_from_its_own_range(): void
    {
        $this->student();
        $q = $this->questionnaire();
        $s = $this->section($q, 'Ten point');
        $x = $this->question($q, $s, 'Rate 1–10', range(1, 10));
        $y = $this->question($q, $s, 'Rate 1–10 reversed', range(1, 10), 'scale', ['is_reverse_scored' => true]);
        $this->levels($q, [[0, 50, 'Lower'], [51, 100, 'Upper']]);

        // 7 + reverse(3) = 7 + 8 = 15 of 20 → 75%.
        $this->submit($q, [
            ['question_id' => $x->id, 'option_id' => $this->worth($x, 7)],
            ['question_id' => $y->id, 'option_id' => $this->worth($y, 3)],
        ])->assertCreated()
            ->assertJsonPath('result.breakdown.categories.0.max_possible_score', 20)
            ->assertJsonPath('result.percentage', 75)
            ->assertJsonPath('result.band.label', 'Upper');

        $overview = app(AssessmentScoringService::class)->overview($q->fresh());
        $this->assertSame(20.0, $overview['sections'][0]['max_raw']);
        $this->assertSame(2.0, $overview['sections'][0]['min_raw']);
        $this->assertSame([10.0, 100.0], $overview['percentage_range']);
    }

    // ------------------------------------------------------------------
    // TEST 6 — Adding and removing questions recalculates the maximum
    // ------------------------------------------------------------------

    public function test_6_maximum_score_follows_the_questions_that_exist(): void
    {
        $admin = $this->admin();
        $q = $this->questionnaire(['status' => 'draft', 'is_active' => false, 'scoring_method' => Questionnaire::SCORING_POINTS_TOTAL]);
        $s = $this->section($q, 'Only');
        $this->question($q, $s, 'One', [1, 2, 3, 4, 5]);
        $this->question($q, $s, 'Two', [1, 2, 3, 4, 5]);

        $engine = app(AssessmentScoringService::class);
        $this->assertSame([2, 10], $engine->overview($q->fresh())['total_span']);

        // Three more through the admin's bulk box: 5 questions → 5–25.
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$q, $s]), [
            'questions_text' => "Three\nFour\nFive", 'scale' => 'agree5',
        ])->assertRedirect();
        $this->assertSame([5, 25], $engine->overview($q->fresh())['total_span']);

        // Remove one through the admin route: 4 questions → 4–20.
        $last = $q->questions()->orderByDesc('questionnaire_questions.position')->first();
        $this->actingAs($admin)->delete(route('admin.questionnaires.sections.questions.destroy', [$q, $s, $last]))->assertRedirect();
        $this->assertSame([4, 20], $engine->overview($q->fresh())['total_span']);

        // Under weighted sections the maximum is the sum of weights, so it
        // does not move with the question count — the percentage does.
        $q->update(['scoring_method' => Questionnaire::SCORING_WEIGHTED_SECTIONS]);
        $this->assertSame([0, 100], $engine->overview($q->fresh())['total_span']);
        $this->actingAs($admin)->get(route('admin.questionnaires.scoring', $q))->assertOk()->assertSee('Assessment overview')->assertSee('4–20');
    }

    // ------------------------------------------------------------------
    // TEST 7 — Any number of result levels, matched from the thresholds
    // ------------------------------------------------------------------

    public function test_7_four_result_levels_are_matched_from_their_configured_thresholds(): void
    {
        $this->student();
        $q = $this->questionnaire();
        $s = $this->section($q, 'S');
        $x = $this->question($q, $s, 'X', range(0, 10));
        $this->levels($q, [[0, 25, 'Low'], [26, 50, 'Moderate'], [51, 75, 'High'], [76, 100, 'Very High']]);

        foreach ([0 => 'Low', 2 => 'Low', 3 => 'Moderate', 5 => 'Moderate', 6 => 'High', 7 => 'High', 8 => 'Very High', 10 => 'Very High'] as $points => $expected) {
            $this->submit($q, [['question_id' => $x->id, 'option_id' => $this->worth($x, $points)]])
                ->assertCreated()
                ->assertJsonPath('result.percentage', $points * 10)
                ->assertJsonPath('result.band.label', $expected);
        }

        // A level not covering the result is a configuration error the
        // engine reports rather than guessing a level.
        $q->scoreBands()->where('label', 'Very High')->update(['is_active' => false]);
        $this->submit($q, [['question_id' => $x->id, 'option_id' => $this->worth($x, 10)]])
            ->assertUnprocessable()->assertJsonValidationErrors(['score_bands']);
    }

    // ------------------------------------------------------------------
    // TEST 8 — History is frozen against the version it was taken on
    // ------------------------------------------------------------------

    public function test_8_a_stored_result_does_not_change_when_the_questionnaire_is_reconfigured(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $q = $this->questionnaire(['section_weighting' => Questionnaire::WEIGHTING_CUSTOM]);
        $a = $this->section($q, 'A', 50);
        $b = $this->section($q, 'B', 50);
        $qa = $this->question($q, $a, 'A1', [1, 2, 3, 4, 5]);
        $qb = $this->question($q, $b, 'B1', [1, 2, 3, 4, 5]);
        $this->levels($q, [[0, 59, 'Low'], [60, 100, 'High']]);

        // A at 100% (50), B at 20% (10) → 60 → High.
        $first = $this->submit($q, [
            ['question_id' => $qa->id, 'option_id' => $this->worth($qa, 5)],
            ['question_id' => $qb->id, 'option_id' => $this->worth($qb, 1)],
        ])->assertCreated()->assertJsonPath('result.total_score', 60)->assertJsonPath('result.band.label', 'High');
        $firstId = $first->json('assessment.id');

        // The admin publishes a new version with different weights, a
        // reversed question and different levels.
        $this->actingAs($admin)->post(route('admin.questionnaires.new-version', $q))->assertRedirect();
        $v2 = Questionnaire::query()->where('type', $q->type)->where('version', 2)->firstOrFail();
        $v2->sections()->where('title', 'A')->update(['category_weight' => 10]);
        $v2->sections()->where('title', 'B')->update(['category_weight' => 90]);
        $v2->questions()->where('question_text', 'A1')->first()->update(['is_reverse_scored' => true]);
        $v2->scoreBands()->update(['is_active' => false]);
        $this->levels($v2, [[0, 100, 'Everything']]);
        $this->actingAs($admin)->patch(route('admin.questionnaires.publish', $v2))->assertRedirect();
        $this->assertTrue($v2->fresh()->is_active);
        $this->assertFalse($q->fresh()->is_active);

        // The same answers on v2 give a different result …
        Sanctum::actingAs($student, ['student']);
        $qa2 = $v2->questions()->with('options')->where('question_text', 'A1')->first();
        $qb2 = $v2->questions()->with('options')->where('question_text', 'B1')->first();
        // A reversed 5 → 1 → 20% × 10 = 2; B 1 → 20% × 90 = 18 → 20 of 100.
        $second = $this->submit($v2, [
            ['question_id' => $qa2->id, 'option_id' => $this->worth($qa2, 5)],
            ['question_id' => $qb2->id, 'option_id' => $this->worth($qb2, 1)],
        ])->assertCreated()->assertJsonPath('result.total_score', 20)->assertJsonPath('result.band.label', 'Everything');

        // … while the first attempt is untouched and still tied to v1.
        $this->assertDatabaseHas('stress_assessments', [
            'id' => $firstId, 'questionnaire_id' => $q->id, 'total_score' => 60, 'stress_level' => 'High',
        ]);
        $stored = StressAssessment::query()->findOrFail($firstId);
        $this->assertSame(1, $stored->config_snapshot['questionnaire']['version']);
        $this->assertSame(50.0, (float) $stored->categoryResults()->orderBy('id')->first()->category_weight);

        $this->getJson("/api/v1/assessments/{$firstId}")->assertOk()
            ->assertJsonPath('data.total_score', 60)
            ->assertJsonPath('data.questionnaire_version', 1)
            ->assertJsonPath('data.band.label', 'High')
            ->assertJsonPath('data.sections.0.weight', 50);
        $this->getJson("/api/v1/assessments/{$second->json('assessment.id')}")->assertOk()
            ->assertJsonPath('data.questionnaire_version', 2);

        $this->getJson('/api/v1/assessments')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.total_score', 60)
            ->assertJsonPath('data.1.questionnaire_version', 1)
            ->assertJsonPath('data.0.questionnaire_version', 2);
    }

    // ------------------------------------------------------------------
    // One questionnaire workflow: one live questionnaire at a time
    // ------------------------------------------------------------------

    public function test_publishing_a_questionnaire_makes_it_the_only_live_one(): void
    {
        $this->student();
        $first = $this->questionnaire(['title' => 'First check-in', 'type' => 'stress']);
        $second = $this->questionnaire([
            'title' => 'Sleep check', 'type' => 'sleep_check',
            'status' => 'draft', 'is_active' => false,
        ]);
        foreach ([$first, $second] as $q) {
            $s = $this->section($q, 'S');
            $this->question($q, $s, 'Q', [1, 2, 3]);
            $this->levels($q, [[0, 100, 'Any']]);
        }

        // The live one is served, and nothing names it in code.
        $this->getJson('/api/v1/questionnaires/active')->assertOk()
            ->assertJsonPath('data.id', $first->id)
            ->assertJsonPath('data.title', 'First check-in');

        // Publishing the second demotes the first, across version families.
        app(QuestionnaireActivationService::class)->activate($second->fresh());
        $this->assertFalse((bool) $first->fresh()->is_active);
        $this->getJson('/api/v1/questionnaires/active')->assertOk()
            ->assertJsonPath('data.id', $second->id);

        // Any completed check-in clears onboarding.
        $this->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('user.has_completed_required_assessment', false);
        $this->submit($second, [[
            'question_id' => $second->questions()->first()->id,
            'option_id' => $second->questions()->with('options')->first()->options->first()->id,
        ]])->assertCreated();
        $this->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('user.has_completed_required_assessment', true);
        $this->getJson('/api/v1/assessments')->assertOk()->assertJsonCount(1, 'data');
    }

    // ------------------------------------------------------------------
    // Recommendations follow the level, not a hardcoded name
    // ------------------------------------------------------------------

    public function test_recommendations_are_the_ones_linked_to_the_matched_level(): void
    {
        $this->student();
        $q = $this->questionnaire();
        $s = $this->section($q, 'S');
        $x = $this->question($q, $s, 'X', [0, 1, 2, 3, 4]);
        $this->levels($q, [[0, 49, 'Settled'], [50, 100, 'Strained']]);
        $counsellor = Intervention::query()->create(['title' => 'Speak with a counsellor', 'content_type' => 'resource', 'is_active' => true]);
        $walk = Intervention::query()->create(['title' => 'Take a short walk', 'content_type' => 'activity', 'is_active' => true]);
        $strained = $q->scoreBands()->where('label', 'Strained')->firstOrFail();
        $settled = $q->scoreBands()->where('label', 'Settled')->firstOrFail();
        InterventionRecommendation::query()->create(['stress_score_band_id' => $strained->id, 'intervention_id' => $counsellor->id, 'priority' => 0, 'is_active' => true]);
        InterventionRecommendation::query()->create(['stress_score_band_id' => $settled->id, 'intervention_id' => $walk->id, 'priority' => 0, 'is_active' => true]);

        $this->submit($q, [['question_id' => $x->id, 'option_id' => $this->worth($x, 4)]])->assertCreated()
            ->assertJsonPath('result.band.label', 'Strained')
            ->assertJsonCount(1, 'recommended_interventions')
            ->assertJsonPath('recommended_interventions.0.title', 'Speak with a counsellor');
        $this->submit($q, [['question_id' => $x->id, 'option_id' => $this->worth($x, 0)]])->assertCreated()
            ->assertJsonPath('result.band.label', 'Settled')
            ->assertJsonPath('recommended_interventions.0.title', 'Take a short walk');
    }

    public function test_a_recommendation_carries_the_app_screen_the_admin_pointed_it_at(): void
    {
        $this->student();
        $q = $this->questionnaire();
        $s = $this->section($q, 'S');
        $x = $this->question($q, $s, 'X', [0, 1]);
        $this->levels($q, [[0, 100, 'Any']]);

        // One piece of support opens a screen in the app, the other a link.
        $journal = Intervention::query()->create([
            'title' => 'Write it down', 'content_type' => 'journaling', 'is_active' => true,
            'app_screen' => AppScreen::Journaling->value,
        ]);
        Intervention::query()->create([
            'title' => 'Read more', 'content_type' => 'resource', 'is_active' => true,
            'external_url' => 'https://example.org/help',
        ]);
        $band = $q->scoreBands()->firstOrFail();
        InterventionRecommendation::query()->create([
            'stress_score_band_id' => $band->id, 'intervention_id' => $journal->id,
            'priority' => 0, 'is_active' => true,
        ]);

        $this->submit($q, [['question_id' => $x->id, 'option_id' => $this->worth($x, 1)]])->assertCreated()
            ->assertJsonPath('recommended_interventions.0.title', 'Write it down')
            ->assertJsonPath('recommended_interventions.0.app_screen', 'screen.journaling')
            ->assertJsonPath('recommended_interventions.1.title', 'Read more')
            ->assertJsonPath('recommended_interventions.1.app_screen', null);
    }
}
