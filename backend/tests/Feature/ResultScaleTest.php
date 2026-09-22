<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Models\User;
use App\Services\AssessmentScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Any number of questions, any answer scoring, any client scale, any
 * ranges: the engine works it all out from configuration.
 */
class ResultScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_questionnaire_takes_the_client_scale_as_entered_and_invents_no_categories(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->post(route('admin.questionnaires.store'), [
                'title' => 'Campus Wellbeing', 'result_scale_min' => 10, 'result_scale_max' => 50,
            ])
            ->assertRedirect();

        $questionnaire = Questionnaire::query()->where('title', 'Campus Wellbeing')->firstOrFail();
        $this->assertSame([10, 50], $questionnaire->resultScale());
        $this->assertSame(0, $questionnaire->scoreBands()->count());

        $this->actingAs($admin)->get(route('admin.questionnaires.scoring', $questionnaire))
            ->assertOk()
            ->assertSee('10–50')
            ->assertSee('10–50');
        $this->actingAs($admin)->get(route('admin.questionnaires.result-levels', $questionnaire))
            ->assertOk()
            ->assertSee('No result ranges yet — add ranges that together cover 10–50.');

        // A backwards scale is refused up front.
        $this->actingAs($admin)
            ->from(route('admin.questionnaires.create'))
            ->post(route('admin.questionnaires.store'), ['title' => 'Bad', 'result_scale_min' => 40, 'result_scale_max' => 0])
            ->assertSessionHasErrors('result_scale_max');
    }

    public function test_a_scale_that_does_not_start_at_zero_maps_proportionally(): void
    {
        // The client's example: raw 75 of 0–100 onto 10–50 → 10 + 0.75 × 40 = 40.
        $this->assertSame(40, AssessmentScoringService::normalise(75, 0, 100, [10, 50]));
        $this->assertSame(10, AssessmentScoringService::normalise(0, 0, 100, [10, 50]));
        $this->assertSame(50, AssessmentScoringService::normalise(100, 0, 100, [10, 50]));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Ten to fifty', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
            'result_scale_min' => 10, 'result_scale_max' => 50,
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Only', 'position' => 1, 'category_weight' => 1, 'is_active' => true,
        ]);
        // Four yes/no questions (0 or 1 point): raw 0–4.
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $section]), [
            'questions_text' => "A\nB\nC\nD", 'scale' => 'yes_no',
        ]);
        foreach ([['minimal', 10, 18], ['mild', 19, 28], ['moderate', 29, 40], ['high', 41, 50]] as [$code, $min, $max]) {
            $questionnaire->scoreBands()->create(['code' => $code, 'label' => ucfirst($code), 'min_score' => $min, 'max_score' => $max, 'scope' => 'overall', 'is_active' => true]);
        }

        Sanctum::actingAs($student, ['student']);
        $questions = $questionnaire->questions()->with('options')->get();
        $answers = $questions->values()->map(fn ($q, $i) => [
            'question_id' => $q->id,
            'option_id' => $q->options->firstWhere('value', $i < 3 ? 'yes' : 'no')->id,
        ])->all();

        // Three of four "yes" = raw 3 of 0–4 → 10 + 0.75 × 40 = 40 → Moderate.
        $this->postJson('/api/v1/assessments', ['questionnaire_id' => $questionnaire->id, 'answers' => $answers])
            ->assertCreated()
            ->assertJsonPath('result.breakdown.overall.raw_score', 3)
            ->assertJsonPath('result.total_score', 40)
            ->assertJsonPath('result.band.code', 'moderate');
    }

    public function test_ranges_outside_the_scale_or_running_backwards_are_refused_on_save(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Wellbeing', 'type' => 'stress', 'version' => 1, 'result_scale_min' => 0, 'result_scale_max' => 40,
        ]);
        $band = fn (int $from, int $to, string $label = 'X') => [
            'scope' => 'overall', 'code' => strtolower($label).$from, 'label' => $label, 'min_score' => $from, 'max_score' => $to, 'position' => 1, 'is_active' => '1',
        ];
        $save = fn (array $bands) => $this->actingAs($admin)
            ->from(route('admin.questionnaires.details', $questionnaire))
            ->patch(route('admin.questionnaires.ranges', $questionnaire), ['bands' => $bands]);

        $save([$band(0, 45, 'Too high')])->assertSessionHasErrors(['bands.0.min_score' => '"Too high" (0–45) falls outside the result scale 0–40.']);
        $save([$band(30, 20, 'Backwards')])->assertSessionHasErrors(['bands.0.min_score' => "\"Backwards\" runs from 30 to 20 — the minimum can't be higher than the maximum."]);
        // The client's own overlap example.
        $save([$band(0, 20, 'Low'), $band(15, 30, 'Mid')])->assertSessionHasErrors('bands');
        $this->assertSame(0, $questionnaire->scoreBands()->count());

        $save([$band(0, 20, 'Low'), $band(21, 40, 'High')])->assertRedirect(route('admin.questionnaires.result-levels', $questionnaire));
        $this->assertSame(2, $questionnaire->scoreBands()->count());
    }

    public function test_a_flat_questionnaire_with_a_scale_is_normalised_too(): void
    {
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Flat', 'type' => 'stress', 'version' => 1, 'result_scale_min' => 0, 'result_scale_max' => 100,
        ]);
        $answers = [];
        foreach ([1, 2, 3] as $i) {
            $question = StressQuestion::query()->create(['question_text' => "Q{$i}", 'question_type' => 'scale', 'is_active' => true]);
            $options = $question->options()->createMany([
                ['label' => 'No', 'value' => 'no', 'score' => 0, 'position' => 1, 'is_active' => true],
                ['label' => 'Yes', 'value' => 'yes', 'score' => 2, 'position' => 2, 'is_active' => true],
            ]);
            $questionnaire->questions()->attach($question->id, ['position' => $i, 'is_required' => true]);
            $answers[$question->id] = $options[$i === 3 ? 0 : 1]->id; // yes, yes, no → raw 4 of 0–6
        }
        $questionnaire->scoreBands()->create(['code' => 'lower', 'label' => 'Lower half', 'min_score' => 0, 'max_score' => 50, 'scope' => 'overall', 'is_active' => true]);
        $questionnaire->scoreBands()->create(['code' => 'upper', 'label' => 'Upper half', 'min_score' => 51, 'max_score' => 100, 'scope' => 'overall', 'is_active' => true]);

        $scored = app(AssessmentScoringService::class)->score($questionnaire, $answers);

        $this->assertSame(67, $scored['total_score']); // 4/6 of 0–100
        $this->assertSame('Upper half', $scored['score_band']->label);
    }
}
