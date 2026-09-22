<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Models\User;
use App\Services\AssessmentScoringService;
use App\Services\QuestionnairePresenter;
use App\Services\QuestionnaireReview;
use App\Services\QuestionnaireVersioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin question builder posts four separate settings — how a question
 * is shown, how it is answered, how the answer becomes points and which way
 * the scale runs — and the review names any combination that cannot work.
 */
class QuestionBuilderConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function draft(): array
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Builder', 'type' => 'builder', 'version' => 1, 'result_scale_min' => 0, 'result_scale_max' => 100,
            'scoring_method' => Questionnaire::SCORING_WEIGHTED_SECTIONS, 'section_weighting' => Questionnaire::WEIGHTING_EQUAL,
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Symptoms', 'position' => 1, 'category_weight' => 1, 'is_active' => true,
        ]);

        return [$admin, $questionnaire, $section];
    }

    public function test_the_question_form_saves_answer_mode_scoring_method_and_direction_separately(): void
    {
        [$admin, $questionnaire, $section] = $this->draft();

        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.store', [$questionnaire, $section]), [
            'question_text' => 'Which of these do you experience?',
            'question_type' => 'multiple_choice',
            'answer_mode' => 'multiple',
            'max_selections' => 3,
            'scoring_method' => 'count_selected',
            'is_reverse_scored' => '0',
            'is_required' => '1',
            'options' => [
                ['label' => 'Poor sleep', 'value' => 'poor_sleep', 'score' => 1],
                ['label' => 'Lack of concentration', 'value' => 'concentration', 'score' => 1],
                ['label' => 'Anxiety', 'value' => 'anxiety', 'score' => 1],
                ['label' => 'Fatigue', 'value' => 'fatigue', 'score' => 1],
            ],
        ])->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));

        $question = StressQuestion::query()->where('question_text', 'Which of these do you experience?')->firstOrFail();
        $this->assertSame('multiple_choice', $question->question_type);
        $this->assertSame('multiple', $question->answer_mode);
        $this->assertSame(3, $question->max_selections);
        $this->assertSame('count_selected', $question->scoring_method);
        $this->assertFalse($question->is_reverse_scored);
        $this->assertSame([0, 3], $question->optionScoreRange());

        // A rating scale with "higher answer = lower score".
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.store', [$questionnaire, $section]), [
            'question_text' => 'I feel calm most of the time',
            'question_type' => 'scale',
            'answer_mode' => 'single',
            'is_reverse_scored' => '1',
            'options' => collect(range(1, 10))->map(fn ($n) => ['label' => (string) $n, 'value' => "p{$n}", 'score' => $n])->all(),
        ])->assertRedirect();
        $calm = StressQuestion::query()->where('question_text', 'I feel calm most of the time')->firstOrFail();
        $this->assertTrue($calm->is_reverse_scored);
        $this->assertSame('single', $calm->answerMode());
        $this->assertSame('direct', $calm->scoringMethod());
        $this->assertSame([1, 10], $calm->optionScoreRange());

        // The builder lists the configuration in plain words.
        $this->actingAs($admin)->get(route('admin.questionnaires.sections.index', $questionnaire))
            ->assertOk()
            ->assertSee('choose several (up to 3)')
            ->assertSee('1 point per tick')
            ->assertSee('higher answer = lower score');

        // The student-facing JSON carries the answer mode and the limit.
        $questionnaire->update(['status' => 'published', 'is_active' => true, 'published_at' => now()]);
        $questionnaire->scoreBands()->create(['code' => 'any', 'label' => 'Any', 'min_score' => 0, 'max_score' => 100, 'scope' => 'overall', 'is_active' => true]);
        $this->getJson('/api/v1/questionnaires/active'); // registration only — not this one
        $this->assertSame(
            ['multiple', 3],
            [app(QuestionnairePresenter::class)->forTaking($questionnaire->load(['sections', 'questions.options']))['questions'][0]['answer_mode'],
                app(QuestionnairePresenter::class)->forTaking($questionnaire)['questions'][0]['max_selections']],
        );
    }

    public function test_impossible_combinations_are_refused_or_named_by_the_review(): void
    {
        [$admin, $questionnaire, $section] = $this->draft();

        // Several answers on a rating scale makes no sense.
        $this->actingAs($admin)->from(route('admin.questionnaires.sections.questions.create', [$questionnaire, $section]))
            ->post(route('admin.questionnaires.sections.questions.store', [$questionnaire, $section]), [
                'question_text' => 'Scale', 'question_type' => 'scale', 'answer_mode' => 'multiple',
                'options' => [['label' => 'a', 'value' => 'a', 'score' => 1], ['label' => 'b', 'value' => 'b', 'score' => 2]],
            ])->assertSessionHasErrors('answer_mode');

        // More ticks than options.
        $this->actingAs($admin)->from(route('admin.questionnaires.sections.questions.create', [$questionnaire, $section]))
            ->post(route('admin.questionnaires.sections.questions.store', [$questionnaire, $section]), [
                'question_text' => 'Many', 'question_type' => 'multiple_choice', 'answer_mode' => 'multiple', 'max_selections' => 5,
                'options' => [['label' => 'a', 'value' => 'a', 'score' => 1], ['label' => 'b', 'value' => 'b', 'score' => 2]],
            ])->assertSessionHasErrors('max_selections');

        // A question whose stored settings drift out of line is named, with
        // its section and number, by the review.
        $question = StressQuestion::query()->create([
            'question_text' => 'Drifted', 'question_type' => 'multiple_choice', 'answer_mode' => 'multiple', 'max_selections' => 9, 'is_active' => true,
        ]);
        $question->options()->createMany([
            ['label' => 'a', 'value' => 'a', 'score' => 1, 'position' => 1, 'is_active' => true],
            ['label' => 'b', 'value' => 'b', 'score' => 2, 'position' => 2, 'is_active' => true],
        ]);
        $questionnaire->questions()->attach($question->id, ['position' => 1, 'is_required' => true, 'questionnaire_section_id' => $section->id]);
        $questionnaire->scoreBands()->create(['code' => 'any', 'label' => 'Any', 'min_score' => 0, 'max_score' => 100, 'scope' => 'overall', 'is_active' => true]);

        $review = app(QuestionnaireReview::class)->run($questionnaire->fresh());
        $issue = collect($review['issues'])->firstWhere('what', 'Question 1 lets students pick 9 answers but only has 2.');
        $this->assertNotNull($issue);
        $this->assertSame('Section "Symptoms"', $issue['where']);
        $this->assertStringContainsString("/questions/{$question->id}/edit", $issue['fix']);
        $this->assertFalse($review['ready']);
    }

    public function test_the_scoring_step_validates_custom_weights_and_derives_equal_ones(): void
    {
        [$admin, $questionnaire, $section] = $this->draft();
        $second = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Sleep', 'position' => 2, 'category_weight' => 1, 'is_active' => true,
        ]);

        // A zero custom weight is refused with the section named.
        $this->actingAs($admin)->from(route('admin.questionnaires.scoring', $questionnaire))
            ->patch(route('admin.questionnaires.scoring.update', $questionnaire), [
                'scoring_method' => 'weighted_sections', 'section_weighting' => 'custom', 'result_basis' => 'percentage',
                'sections' => [['id' => $section->id, 'category_weight' => 0], ['id' => $second->id, 'category_weight' => 70]],
            ])->assertSessionHasErrors('sections.0.category_weight');

        $this->actingAs($admin)->patch(route('admin.questionnaires.scoring.update', $questionnaire), [
            'scoring_method' => 'weighted_sections', 'section_weighting' => 'custom', 'result_basis' => 'percentage',
            'sections' => [['id' => $section->id, 'category_weight' => 30], ['id' => $second->id, 'category_weight' => 70]],
        ])->assertRedirect(route('admin.questionnaires.scoring', $questionnaire));
        $this->assertSame(30.0, (float) $section->fresh()->category_weight);
        $this->assertSame(70.0, (float) $second->fresh()->category_weight);

        // Equal weighting ignores the stored numbers and shares 100 evenly.
        $this->actingAs($admin)->patch(route('admin.questionnaires.scoring.update', $questionnaire), [
            'scoring_method' => 'weighted_sections', 'section_weighting' => 'equal', 'result_basis' => 'percentage',
        ])->assertRedirect();
        $weights = AssessmentScoringService::effectiveSectionWeights($questionnaire->fresh(), $questionnaire->sections()->get());
        $this->assertSame([50.0, 50.0], array_values($weights));
        $this->assertSame(30.0, (float) $section->fresh()->category_weight, 'stored weights are kept for when custom is chosen again');

        $this->actingAs($admin)->get(route('admin.questionnaires.scoring', $questionnaire))->assertOk()->assertSee('Assessment overview')->assertSee('Equal');
    }

    public function test_versions_and_duplicates_carry_the_whole_configuration(): void
    {
        [$admin, $questionnaire, $section] = $this->draft();
        $questionnaire->update(['estimated_minutes' => 12, 'section_weighting' => Questionnaire::WEIGHTING_CUSTOM]);
        $question = StressQuestion::query()->create([
            'question_text' => 'Multi', 'question_type' => 'multiple_choice', 'answer_mode' => 'multiple', 'max_selections' => 2,
            'scoring_method' => 'max_selected', 'is_reverse_scored' => true, 'wellbeing_weight' => 2, 'is_active' => true,
        ]);
        $question->options()->createMany([
            ['label' => 'a', 'value' => 'a', 'score' => 1, 'position' => 1, 'is_active' => true],
            ['label' => 'b', 'value' => 'b', 'score' => 2, 'position' => 2, 'is_active' => true],
            ['label' => 'old', 'value' => 'old', 'score' => 9, 'position' => 3, 'is_active' => false],
        ]);
        $questionnaire->questions()->attach($question->id, ['position' => 1, 'is_required' => true, 'questionnaire_section_id' => $section->id]);
        $band = $questionnaire->scoreBands()->create(['code' => 'any', 'label' => 'Any', 'description' => 'd', 'harmony_message' => 'm', 'min_score' => 0, 'max_score' => 100, 'scope' => 'overall', 'is_active' => true]);
        $intervention = Intervention::query()->create(['title' => 'Walk', 'content_type' => 'activity', 'is_active' => true]);
        $band->recommendations()->create(['intervention_id' => $intervention->id, 'priority' => 0, 'is_active' => true]);

        $versioner = app(QuestionnaireVersioner::class);
        $v2 = $versioner->draftFrom($questionnaire, $admin->id);
        $copy = $versioner->duplicate($questionnaire, $admin->id);

        foreach ([$v2, $copy] as $clone) {
            $this->assertSame([0, 100], $clone->resultScale());
            $this->assertSame(Questionnaire::SCORING_WEIGHTED_SECTIONS, $clone->scoringMethod());
            $this->assertFalse($clone->usesEqualSectionWeights());
            $this->assertSame(12, $clone->estimated_minutes);
            $q = $clone->questions()->with('options')->firstOrFail();
            $this->assertSame(['multiple', 2, 'max_selected', true, 2.0], [$q->answer_mode, $q->max_selections, $q->scoring_method, (bool) $q->is_reverse_scored, (float) $q->wellbeing_weight]);
            $this->assertSame(2, $q->options->count(), 'inactive options are not resurrected');
            $b = $clone->scoreBands()->firstOrFail();
            $this->assertSame(['d', 'm'], [$b->description, $b->harmony_message]);
            $this->assertSame($intervention->id, (int) $b->recommendations()->where('is_active', true)->value('intervention_id'));
        }
        $this->assertSame([$questionnaire->type, 2], [$v2->type, $v2->version]);
        $this->assertNotSame($questionnaire->type, $copy->type);
        $this->assertSame(['Builder (copy)', 1, Questionnaire::PURPOSE_LIBRARY], [$copy->title, $copy->version, $copy->purpose]);

        // Duplicating a section copies its questions as fresh rows.
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.duplicate', [$questionnaire, $section]))->assertRedirect();
        $this->assertSame(2, $questionnaire->sections()->count());
        $this->assertSame(2, $questionnaire->questions()->count());
        $this->assertSame(1, $questionnaire->sections()->where('title', 'Symptoms (copy)')->count());
    }
}
