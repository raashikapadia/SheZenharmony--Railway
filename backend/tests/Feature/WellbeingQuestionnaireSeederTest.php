<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\StressScoreBand;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StressFrameworkSeeder;
use Database\Seeders\WellbeingQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WellbeingQuestionnaireSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_the_74_question_8_section_instrument_and_activates_it(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(StressFrameworkSeeder::class);
        $this->seed(WellbeingQuestionnaireSeeder::class);

        $questionnaire = Questionnaire::query()->where('title', WellbeingQuestionnaireSeeder::TITLE)->firstOrFail();

        $this->assertSame('published', $questionnaire->status);
        $this->assertTrue((bool) $questionnaire->is_active);
        $this->assertSame(8, $questionnaire->sections()->count());
        $this->assertSame(74, $questionnaire->questions()->count());

        $this->assertEqualsWithDelta(40.0, (float) $questionnaire->sections()->sum('category_weight'), 0.001);
        $questionnaire->sections->each(fn ($section) => $this->assertEqualsWithDelta(5.0, (float) $section->category_weight, 0.001));

        // Every question sits in a section and is required.
        $this->assertSame(0, $questionnaire->questions()->wherePivotNull('questionnaire_section_id')->count());
        $this->assertSame(74, $questionnaire->questions()->wherePivot('is_required', true)->count());

        // The client scale is fixed at 0–40 whatever the question count; the
        // four bands cover it end to end.
        $bands = $questionnaire->scoreBands()->where('scope', StressScoreBand::SCOPE_OVERALL)->orderBy('min_score')->get();
        $this->assertSame(0, $bands->first()->min_score);
        $this->assertSame(40, $bands->last()->max_score);
        $this->assertSame([0, 40], $questionnaire->resultScale());

        // Publishing the wellbeing questionnaire returns the starter to draft.
        $starter = Questionnaire::query()->where('title', StressFrameworkSeeder::QUESTIONNAIRE_TITLE)->firstOrFail();
        $this->assertSame('draft', $starter->status);
        $this->assertFalse((bool) $starter->is_active);
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(WellbeingQuestionnaireSeeder::class);
        $this->seed(WellbeingQuestionnaireSeeder::class);

        $this->assertSame(1, Questionnaire::query()->where('title', WellbeingQuestionnaireSeeder::TITLE)->count());
    }

    public function test_a_student_can_submit_all_74_questions_in_one_assessment(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(WellbeingQuestionnaireSeeder::class);

        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        Sanctum::actingAs($student, ['student']);

        $questionnaire = Questionnaire::query()
            ->where('title', WellbeingQuestionnaireSeeder::TITLE)
            ->with('questions.options')
            ->firstOrFail();

        $answers = $questionnaire->questions->map(fn ($question) => [
            'question_id' => $question->id,
            'option_id' => $question->options->firstWhere('score', 3)->id,
        ])->values()->all();

        $this->postJson('/api/v1/assessments', [
            'questionnaire_id' => $questionnaire->id,
            'answers' => $answers,
        ])->assertCreated()
            ->assertJsonCount(8, 'result.breakdown.categories')
            ->assertJsonPath('result.breakdown.overall.max_weighted_score', 40)
            // 74 answers of 3 points = raw 222 of 74–370, which is exactly half
            // way: 20 on the 0–40 client scale.
            ->assertJsonPath('result.breakdown.overall.raw_score', 222)
            ->assertJsonPath('result.breakdown.overall.raw_min', 74)
            ->assertJsonPath('result.breakdown.overall.raw_max', 370)
            ->assertJsonPath('result.total_score', 20)
            ->assertJsonPath('result.band.code', 'low-2');

        $assessment = $student->studentIdentity()->firstOrFail()->assessments()->firstOrFail();
        $this->assertSame(74, $assessment->responses()->count());
        $this->assertSame(8, $assessment->categoryResults()->count());
    }
}
