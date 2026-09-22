<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\InterventionRecommendation;
use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The simplified builder: one page, Questionnaire → Section → Questions →
 * Answers, with result ranges that know what they have to cover.
 */
class QuestionnaireBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_section_removes_its_questions_from_the_questionnaire(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Going', 'position' => 1, 'category_weight' => 1, 'is_active' => true,
        ]);
        $keep = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Staying', 'position' => 2, 'category_weight' => 1, 'is_active' => true,
        ]);
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $section]), ['questions_text' => "A\nB", 'scale' => 'agree5']);
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $keep]), ['questions_text' => 'C', 'scale' => 'agree5']);
        $goingIds = $questionnaire->questions()->wherePivot('questionnaire_section_id', $section->id)->pluck('stress_questions.id');

        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.sections.destroy', [$questionnaire, $section]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Section "Going" and its 2 questions deleted.');

        $this->assertDatabaseMissing('questionnaire_sections', ['id' => $section->id]);
        foreach ($goingIds as $id) {
            $this->assertDatabaseMissing('stress_questions', ['id' => $id]);
        }
        // Nothing is left behind for students to answer; the other section is untouched.
        $this->assertSame(['C'], $questionnaire->questions()->pluck('question_text')->all());
    }

    public function test_the_editor_points_out_gaps_and_overlaps_in_the_result_ranges(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        // Two sections, two 1–5 questions each: a student's points total runs 4–20.
        foreach (['One', 'Two'] as $i => $title) {
            $section = QuestionnaireSection::query()->create([
                'questionnaire_id' => $questionnaire->id, 'title' => $title, 'position' => $i + 1, 'category_weight' => 5, 'is_active' => true,
            ]);
            $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $section]), [
                'questions_text' => "{$title} A\n{$title} B", 'scale' => 'agree5',
            ]);
        }
        $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 4, 'max_score' => 9, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 'high', 'label' => 'High', 'min_score' => 13, 'max_score' => 19, 'scope' => 'overall', 'is_active' => true]);

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.result-levels', $questionnaire))
            ->assertOk()
            ->assertSee('between <strong>4</strong> and <strong>20</strong>', false)
            ->assertSee('Scores 10–12 are not covered by any range.')
            ->assertSee('Score 20 is not covered by any range.');

        $questionnaire->scoreBands()->create(['code' => 'mid', 'label' => 'Mid', 'min_score' => 9, 'max_score' => 12, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->where('code', 'high')->update(['max_score' => 20]);

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.result-levels', $questionnaire))
            ->assertOk()
            ->assertSee('&quot;Low&quot; (4–9) and &quot;Mid&quot; (9–12) overlap.', false);

        $questionnaire->scoreBands()->where('code', 'mid')->update(['min_score' => 10]);

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.result-levels', $questionnaire))
            ->assertOk()
            ->assertSee('Every score from 4 to 20 has a level.');

        // Adding a question widens the span, and the panel says so at once.
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $section]), [
            'questions_text' => 'Two C', 'scale' => 'agree5',
        ]);
        $this->actingAs($admin)
            ->get(route('admin.questionnaires.result-levels', $questionnaire))
            ->assertOk()
            ->assertSee('between <strong>5</strong> and <strong>25</strong>', false)
            ->assertSee('Scores 21–25 are not covered by any range.');
    }

    public function test_a_range_can_name_the_support_item_to_recommend_first(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $band = $questionnaire->scoreBands()->create(['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 10, 'scope' => 'overall', 'is_active' => true]);
        $breathing = Intervention::query()->create(['title' => 'Breathing exercise', 'content_type' => 'journaling', 'is_active' => true]);
        $journal = Intervention::query()->create(['title' => 'Guided journaling', 'content_type' => 'journaling', 'is_active' => true]);
        // Already linked from the support-content side.
        InterventionRecommendation::query()->create(['stress_score_band_id' => $band->id, 'intervention_id' => $journal->id, 'priority' => 0, 'is_active' => true]);

        $save = fn ($interventionId) => $this->actingAs($admin)->patch(route('admin.questionnaires.ranges', $questionnaire), [
            'bands' => [[
                'id' => $band->id, 'scope' => 'overall', 'code' => 'low', 'label' => 'Low stress',
                'min_score' => 0, 'max_score' => 10, 'position' => 1, 'is_active' => '1',
                'intervention_id' => $interventionId,
            ]],
        ])->assertRedirect(route('admin.questionnaires.result-levels', $questionnaire));

        $save($breathing->id);
        $first = $band->recommendations()->where('is_active', true)->orderBy('priority')->orderBy('id')->first();
        $this->assertSame($breathing->id, (int) $first->intervention_id);
        // The earlier link is kept, just no longer first.
        $this->assertTrue($band->recommendations()->where('intervention_id', $journal->id)->where('is_active', true)->exists());
        $this->assertSame('Low stress', $band->fresh()->label);

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.result-levels', $questionnaire))
            ->assertOk()
            ->assertSee('<option value="'.$breathing->id.'" selected', false);

        $save('');
        $this->assertFalse($band->recommendations()->where('intervention_id', $breathing->id)->where('is_active', true)->exists());
    }

    public function test_the_builder_no_longer_offers_an_advanced_editor_or_question_bank(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 3]);

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.sections.index', $questionnaire))
            ->assertOk()
            ->assertSee('No sections yet')
            ->assertSee('Create your first section to start building your questionnaire.')
            ->assertDontSee('Advanced editor')
            ->assertDontSee('question bank')
            ->assertDontSee('v3');
    }

    public function test_raw_scores_are_normalised_onto_the_client_result_scale(): void
    {
        $scale = [0, 40];
        // The client's own worked examples.
        $this->assertSame(30, \App\Services\AssessmentScoringService::normalise(15, 0, 20, $scale));
        $this->assertSame(30, \App\Services\AssessmentScoringService::normalise(30, 0, 40, $scale));
        $this->assertSame(30, \App\Services\AssessmentScoringService::normalise(111, 0, 148, $scale));
        // A raw minimum above zero (e.g. 1–5 answers) is the scale's floor.
        $this->assertSame(0, \App\Services\AssessmentScoringService::normalise(74, 74, 370, $scale));
        $this->assertSame(40, \App\Services\AssessmentScoringService::normalise(370, 74, 370, $scale));
        // No scale configured: the raw total is the result.
        $this->assertSame(111, \App\Services\AssessmentScoringService::normalise(111, 0, 148, null));
    }

    public function test_adding_questions_changes_the_raw_span_but_never_the_client_scale(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Wellbeing', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
            'result_scale_min' => 0, 'result_scale_max' => 40,
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Only', 'position' => 1, 'category_weight' => 1, 'is_active' => true,
        ]);
        foreach ([['low', 0, 10], ['mild', 11, 20], ['moderate', 21, 30], ['high', 31, 40]] as [$code, $min, $max]) {
            $questionnaire->scoreBands()->create(['code' => $code, 'label' => ucfirst($code), 'min_score' => $min, 'max_score' => $max, 'scope' => 'overall', 'is_active' => true]);
        }

        $editor = fn () => $this->actingAs($admin)->get(route('admin.questionnaires.result-levels', $questionnaire))->assertOk();
        $submit = function (int $points) use ($student, $questionnaire) {
            \Laravel\Sanctum\Sanctum::actingAs($student, ['student']);
            $answers = $questionnaire->questions()->with('options')->get()->map(fn ($q) => [
                'question_id' => $q->id,
                'option_id' => $q->options->firstWhere('score', $points)->id,
            ])->values()->all();

            return $this->postJson('/api/v1/assessments', ['questionnaire_id' => $questionnaire->id, 'answers' => $answers])->assertCreated();
        };

        // Ten 1–5 questions: raw 10–50. All "4"s = raw 40 → 75% → 30 → Moderate.
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $section]), [
            'questions_text' => implode("\n", array_map(fn ($i) => "Q{$i}", range(1, 10))), 'scale' => 'agree5',
        ]);
        $editor()->assertSee('10–50')->assertSee('0–40')->assertSee('Every score from 0 to 40 has a level.');
        $submit(4)->assertJsonPath('result.breakdown.overall.raw_score', 40)
            ->assertJsonPath('result.total_score', 30)
            ->assertJsonPath('result.band.code', 'moderate');

        // Double the questions: raw span moves, the scale and ranges do not,
        // and the same answers still land on the same result.
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $section]), [
            'questions_text' => implode("\n", array_map(fn ($i) => "Q{$i}", range(11, 20))), 'scale' => 'agree5',
        ]);
        $editor()->assertSee('20–100')->assertSee('0–40')->assertSee('Every score from 0 to 40 has a level.');
        $submit(4)->assertJsonPath('result.breakdown.overall.raw_score', 80)
            ->assertJsonPath('result.total_score', 30)
            ->assertJsonPath('result.band.code', 'moderate');
        $submit(5)->assertJsonPath('result.total_score', 40)->assertJsonPath('result.band.code', 'high');
        $submit(1)->assertJsonPath('result.total_score', 0)->assertJsonPath('result.band.code', 'low');
    }

    public function test_the_client_scale_is_saved_with_the_ranges_and_must_stay_covered(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Wellbeing', 'type' => 'stress', 'version' => 1,
            'result_scale_min' => 0, 'result_scale_max' => 40,
        ]);
        $band = $questionnaire->scoreBands()->create(['code' => 'all', 'label' => 'All', 'min_score' => 0, 'max_score' => 40, 'scope' => 'overall', 'is_active' => true]);

        $this->actingAs($admin)->patch(route('admin.questionnaires.ranges', $questionnaire), [
            'result_scale_min' => 0, 'result_scale_max' => 100,
            'bands' => [['id' => $band->id, 'scope' => 'overall', 'code' => 'all', 'label' => 'All', 'min_score' => 0, 'max_score' => 40, 'position' => 1, 'is_active' => '1']],
        ])->assertRedirect(route('admin.questionnaires.result-levels', $questionnaire));

        $this->assertSame([0, 100], $questionnaire->fresh()->resultScale());
        $this->actingAs($admin)->get(route('admin.questionnaires.result-levels', $questionnaire))
            ->assertOk()
            ->assertSee('Scores 41–100 are not covered by any range.');

        $this->actingAs($admin)->from(route('admin.questionnaires.details', $questionnaire))
            ->patch(route('admin.questionnaires.ranges', $questionnaire), [
                'result_scale_min' => 50, 'result_scale_max' => 10, 'bands' => [],
            ])->assertSessionHasErrors('result_scale_max');
    }
}
