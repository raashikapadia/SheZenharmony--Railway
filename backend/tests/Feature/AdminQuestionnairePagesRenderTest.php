<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The questionnaire / section / question admin pages gained new fields in
 * this pass. These render checks catch Blade regressions the JSON-only
 * admin API tests would miss.
 */
class AdminQuestionnairePagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_configuration_pages_render_for_an_admin(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $questionnaire = Questionnaire::query()->create([
            'title' => 'Wellbeing', 'type' => 'stress', 'version' => 1,
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Emotional',
            'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);
        $question = StressQuestion::query()->create([
            'question_text' => 'I feel calm.', 'question_type' => 'scale', 'is_active' => true,
        ]);
        $question->options()->createMany([
            ['label' => 'No', 'value' => 'no', 'score' => 1, 'position' => 1, 'is_active' => true],
            ['label' => 'Yes', 'value' => 'yes', 'score' => 5, 'position' => 2, 'is_active' => true],
        ]);
        $questionnaire->questions()->attach($question->id, [
            'position' => 1, 'is_required' => true, 'questionnaire_section_id' => $section->id,
        ]);
        $questionnaire->scoreBands()->create([
            'code' => 'ok', 'label' => 'OK', 'min_score' => 0, 'max_score' => 5, 'scope' => 'overall', 'is_active' => true,
        ]);

        $this->actingAs($admin);

        // Assessment has two entries: Questionnaire Management + Stress Level Assessment.
        $this->get(route('admin.questionnaires.index'))->assertOk()
            ->assertSee('Questionnaire Management')
            ->assertSee('Stress Level Assessment')
            ->assertDontSee('Question Builder');

        $this->get(route('admin.questionnaires.create'))->assertOk();
        $this->get(route('admin.questionnaires.edit', $questionnaire))->assertOk()->assertSee('Manage sections');
        $this->get(route('admin.questionnaires.scoring', $questionnaire))->assertOk()->assertSee('Scoring overview');
        $this->get(route('admin.questionnaires.sections.index', $questionnaire))->assertOk()->assertSee('Emotional');
        $this->get(route('admin.questionnaires.sections.create', $questionnaire))->assertOk()->assertSee('Category weight');
        $this->get(route('admin.questionnaires.sections.edit', [$questionnaire, $section]))->assertOk();
        $this->get(route('admin.questions.create'))->assertOk()->assertSee('Advanced scoring settings');
        $this->get(route('admin.questions.edit', $question))->assertOk()->assertSee('Reverse scoring');
    }
}
