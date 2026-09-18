<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin assessment area exposes exactly two sidebar items — Questionnaire
 * Management and Assessments — and everything else is a tab
 * inside them, not a separate navigation page.
 */
class AdminAssessmentNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function questionnaire(): Questionnaire
    {
        $q = Questionnaire::query()->create([
            'title' => 'SheZen Wellbeing Questionnaire', 'type' => 'stress', 'version' => 3,
            'status' => 'published', 'is_active' => true, 'published_at' => now(),
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $q->id, 'title' => 'Emotional', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);
        $question = StressQuestion::query()->create(['question_text' => 'I feel calm.', 'question_type' => 'scale', 'min_score' => 1, 'max_score' => 5, 'is_active' => true]);
        foreach (range(1, 5) as $s) {
            $question->options()->create(['label' => "S{$s}", 'value' => "s{$s}", 'score' => $s, 'position' => $s, 'is_active' => true]);
        }
        $q->questions()->attach($question->id, ['position' => 1, 'is_required' => true, 'questionnaire_section_id' => $section->id]);
        $q->scoreBands()->create(['code' => 'ok', 'label' => 'OK', 'min_score' => 0, 'max_score' => 5, 'scope' => 'overall', 'is_active' => true]);

        return $q;
    }

    public function test_questionnaire_management_has_five_tabs_all_reachable(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->questionnaire();
        $this->actingAs($admin);

        foreach ([
            'admin.questionnaires.index',      // Overview
            'admin.questionnaires.results',
            'admin.questionnaires.analytics',
            'admin.questionnaires.versions',
        ] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('aria-label="Questionnaire Management"', false)   // the tab bar
                ->assertSee('Questionnaire Builder')
                ->assertSee('>Versions</a>', false);
        }

        // Builder tab redirects into the one editor (no separate builder page).
        $this->get(route('admin.questionnaires.builder'))
            ->assertRedirect(route('admin.questionnaires.sections.index', Questionnaire::query()->firstOrFail()));
    }

    public function test_stress_level_assessment_has_three_tabs(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);

        foreach (['admin.student-stress.overview', 'admin.student-stress.index', 'admin.student-stress.analytics'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('aria-label="Assessments"', false)
                ->assertSee('Overview')->assertSee('Results')->assertSee('Analytics')
                ->assertDontSee('aria-label="Questionnaire Management"', false);   // not the questionnaire tab bar
        }
    }

    public function test_analytics_sub_tabs_render_from_one_dataset(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin);

        foreach (['overall', 'categories', 'questions', 'demographics', 'vs', 'trends', 'factors', 'protective'] as $tab) {
            $this->get(route('admin.questionnaires.analytics', ['tab' => $tab]))->assertOk();
            $this->get(route('admin.student-stress.analytics', ['tab' => $tab]))->assertOk();
        }
    }

    public function test_students_cannot_reach_any_of_the_new_tabs(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $this->actingAs($student);

        foreach ([
            'admin.questionnaires.builder', 'admin.questionnaires.results',
            'admin.questionnaires.analytics', 'admin.questionnaires.versions',
            'admin.student-stress.overview',
        ] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }
}
