<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /** A small, valid, weighted questionnaire: 1 section (weight 5), 1 question (1..5), bands 0-5. */
    private function questionnaire(string $status = 'published', int $version = 3): Questionnaire
    {
        $questionnaire = Questionnaire::query()->create([
            'title' => 'SheZen Wellbeing Questionnaire',
            'description' => 'Dynamic wellbeing check-in across eight life areas.',
            'type' => 'stress', 'version' => $version,
            'status' => $status, 'is_active' => $status === 'published',
            'published_at' => $status === 'published' ? now() : null,
        ]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Emotional', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);
        $question = StressQuestion::query()->create([
            'question_text' => 'I feel calm.', 'question_type' => 'scale', 'min_score' => 1, 'max_score' => 5, 'is_active' => true,
        ]);
        foreach (range(1, 5) as $s) {
            $question->options()->create(['label' => "S{$s}", 'value' => "s{$s}", 'score' => $s, 'position' => $s, 'is_active' => true]);
        }
        $questionnaire->questions()->attach($question->id, ['position' => 1, 'is_required' => true, 'questionnaire_section_id' => $section->id]);
        $questionnaire->scoreBands()->create(['code' => 'ok', 'label' => 'OK', 'min_score' => 0, 'max_score' => 5, 'scope' => 'overall', 'is_active' => true]);

        return $questionnaire;
    }

    public function test_overview_is_a_single_list_of_every_questionnaire(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->questionnaire();

        $this->actingAs($admin)->get(route('admin.questionnaires.index'))
            ->assertOk()
            ->assertSee('All questionnaires (1)')
            ->assertSee('SheZen Wellbeing Questionnaire')
            ->assertSee('v3')
            ->assertSee('Trash (0)');

        // The numeric breakdown lives on Analytics.
        $this->actingAs($admin)->get(route('admin.questionnaires.analytics'))
            ->assertOk()
            ->assertSee('Questionnaire structure')
            ->assertSee('Max weighted')
            ->assertSee('By section');
    }

    public function test_max_scores_are_computed_from_the_data_not_hardcoded(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = $this->questionnaire();

        // 1 question, max option score 5 -> max raw 5. 1 active section weight 5 -> max weighted 5.
        $this->actingAs($admin)->get(route('admin.questionnaires.analytics'))->assertOk()->assertSeeText('5');

        // Bump the section weight -> max weighted follows.
        $questionnaire->sections()->update(['category_weight' => 9]);
        $this->actingAs($admin)->get(route('admin.questionnaires.analytics'))->assertOk()->assertSeeText('9');
    }

    public function test_editor_reports_the_publish_blocker_when_invalid(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = $this->questionnaire('draft');
        // Break coverage: band no longer reaches the weighted max.
        $questionnaire->scoreBands()->update(['max_score' => 1]);

        $this->actingAs($admin)->get(route('admin.questionnaires.sections.index', $questionnaire))
            ->assertOk()
            ->assertSee('Not ready to publish yet')
            ->assertDontSee('Publish now');
    }

    public function test_create_draft_forks_a_new_version_and_leaves_the_live_one_untouched(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $live = $this->questionnaire();

        $this->actingAs($admin)
            ->post(route('admin.questionnaires.new-version', $live))
            ->assertRedirect();

        $draft = Questionnaire::query()->where('status', 'draft')->firstOrFail();
        $this->assertSame(4, $draft->version);
        $this->assertFalse((bool) $draft->is_active);
        $this->assertSame(1, $draft->sections()->count());
        $this->assertSame(1, $draft->questions()->count());
        $this->assertSame(1, $draft->scoreBands()->count());

        // Live version unchanged and still the only active one.
        $live->refresh();
        $this->assertTrue((bool) $live->is_active);
        $this->assertSame(3, $live->version);
        $this->assertSame(1, Questionnaire::query()->where('is_active', true)->count());
    }

    public function test_overview_lists_a_draft_alongside_the_live_version(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->questionnaire();
        Questionnaire::query()->create([
            'title' => 'Next semester draft', 'type' => 'stress', 'version' => 4, 'status' => 'draft', 'is_active' => false,
        ]);

        $this->actingAs($admin)->get(route('admin.questionnaires.index'))
            ->assertOk()
            ->assertSee('All questionnaires (2)')
            ->assertSee('Next semester draft')
            ->assertSee('v4')
            // Drafts sort above the live version, and only the draft can be published.
            ->assertSeeInOrder(['Next semester draft', 'SheZen Wellbeing Questionnaire']);
    }

    public function test_preview_renders_the_questions_without_creating_an_attempt(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = $this->questionnaire();

        $this->actingAs($admin)->get(route('admin.questionnaires.preview', $questionnaire))
            ->assertOk()
            ->assertSee('I feel calm.')
            ->assertSee('no result is saved');

        $this->assertSame(0, StressAssessment::query()->count());
    }

    public function test_one_click_publish_makes_a_version_live_and_returns_all_others_to_draft(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $live = $this->questionnaire('published', 3);
        $draft = $this->questionnaire('draft', 5);
        $archived = $this->questionnaire('archived', 2);

        $this->actingAs($admin)
            ->patch(route('admin.questionnaires.publish', $draft))
            ->assertRedirect();

        $this->assertDatabaseHas('questionnaires', ['id' => $draft->id, 'status' => 'published', 'is_active' => true]);
        $this->assertDatabaseHas('questionnaires', ['id' => $live->id, 'status' => 'draft', 'is_active' => false]);
        $this->assertDatabaseHas('questionnaires', ['id' => $archived->id, 'status' => 'draft', 'is_active' => false]);
        $this->assertSame(1, Questionnaire::query()->where('is_active', true)->count());
    }

    public function test_publish_button_is_shown_for_a_non_live_questionnaire_and_hidden_for_the_live_one(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $q = $this->questionnaire('draft');

        $this->actingAs($admin)->get(route('admin.questionnaires.index'))
            ->assertOk()
            ->assertSee(route('admin.questionnaires.publish', $q), false)
            ->assertSee('Publish now');

        $q->update(['status' => 'published', 'is_active' => true]);
        $this->actingAs($admin)->get(route('admin.questionnaires.index'))
            ->assertOk()
            ->assertDontSee('Publish now');
    }

    public function test_publish_of_an_incomplete_questionnaire_reports_the_blocker_instead_of_erroring(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $q = $this->questionnaire('draft');
        $q->scoreBands()->update(['max_score' => 1]);   // coverage gap

        $this->actingAs($admin)
            ->patch(route('admin.questionnaires.publish', $q))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Not published'));

        $this->assertDatabaseHas('questionnaires', ['id' => $q->id, 'is_active' => false]);
    }

    public function test_overview_lists_every_questionnaire(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->questionnaire('published', 3);
        $second = $this->questionnaire('draft', 9);
        $second->update(['title' => 'Second draft']);

        $this->actingAs($admin)->get(route('admin.questionnaires.index'))
            ->assertOk()
            ->assertSee('All questionnaires (2)')
            ->assertSee('Second draft')
            ->assertSee('v9');
    }

    public function test_editor_guides_a_new_draft_through_steps_and_offers_keep_as_draft(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $draft = $this->questionnaire('draft');

        $this->actingAs($admin)->get(route('admin.questionnaires.sections.index', $draft))
            ->assertOk()
            ->assertSee('Questionnaire setup progress', false)
            ->assertSee('Publish now')
            ->assertSee('Keep as draft');
    }

    public function test_editor_drops_the_wizard_once_the_questionnaire_is_live(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $live = $this->questionnaire('published');

        $this->actingAs($admin)->get(route('admin.questionnaires.sections.index', $live))
            ->assertOk()
            ->assertDontSee('Questionnaire setup progress', false)
            ->assertSee('live for students');
    }

    public function test_preview_looks_like_the_shezen_app_and_pages_through_questions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = $this->questionnaire();

        $this->actingAs($admin)->get(route('admin.questionnaires.preview', $questionnaire))
            ->assertOk()
            ->assertSee('Begin stress check', false)
            ->assertSee('I feel calm.')
            ->assertSee('1 of 1')
            ->assertSee('no result is saved');
    }

    public function test_students_cannot_open_the_workspace_or_preview(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $questionnaire = $this->questionnaire();

        $this->actingAs($student)->get(route('admin.questionnaires.index'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.questionnaires.preview', $questionnaire))->assertForbidden();
        $this->actingAs($student)->post(route('admin.questionnaires.new-version', $questionnaire))->assertForbidden();
    }
}
