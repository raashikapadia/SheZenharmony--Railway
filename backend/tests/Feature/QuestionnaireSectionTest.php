<?php

namespace Tests\Feature;

use App\Models\CategoryResult;
use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_reposition_sections(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);

        $this->actingAs($admin)
            ->post(route('admin.questionnaires.sections.store', $questionnaire), [
                'title' => 'Emotional', 'category_weight' => 5, 'is_active' => '1',
            ])
            ->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));

        $section = $questionnaire->sections()->firstOrFail();
        $this->assertSame('Emotional', $section->title);
        $this->assertEqualsWithDelta(5.0, (float) $section->category_weight, 0.001);
        $this->assertSame(1, $section->position);

        $this->actingAs($admin)
            ->put(route('admin.questionnaires.sections.update', [$questionnaire, $section]), [
                'title' => 'Emotional & Psychological', 'category_weight' => 7, 'position' => 3, 'is_active' => '1',
            ])
            ->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));

        $section->refresh();
        $this->assertSame('Emotional & Psychological', $section->title);
        $this->assertEqualsWithDelta(7.0, (float) $section->category_weight, 0.001);
        $this->assertSame(3, $section->position);
    }

    public function test_zero_or_negative_category_weight_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);

        $this->actingAs($admin)
            ->from(route('admin.questionnaires.sections.create', $questionnaire))
            ->post(route('admin.questionnaires.sections.store', $questionnaire), [
                'title' => 'Bad', 'category_weight' => 0,
            ])
            ->assertSessionHasErrors('category_weight');

        $this->assertSame(0, $questionnaire->sections()->count());
    }

    public function test_a_section_referenced_by_a_historical_result_is_archived_not_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'History', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);
        $assessment = StressAssessment::query()->create(['user_id' => $admin->id]);
        CategoryResult::query()->create([
            'stress_assessment_id' => $assessment->id,
            'questionnaire_section_id' => $section->id,
            'section_title_snapshot' => 'History',
            'raw_score' => 1, 'min_possible_score' => 0, 'max_possible_score' => 5,
            'percentage' => 20, 'category_weight' => 5, 'weighted_score' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.sections.destroy', [$questionnaire, $section]))
            ->assertRedirect();

        $this->assertDatabaseHas('questionnaire_sections', ['id' => $section->id, 'is_active' => false]);
    }

    public function test_an_unused_section_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Spare', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.sections.destroy', [$questionnaire, $section]))
            ->assertRedirect();

        $this->assertDatabaseMissing('questionnaire_sections', ['id' => $section->id]);
    }

    public function test_sections_from_another_questionnaire_are_not_editable_here(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $a = Questionnaire::query()->create(['title' => 'A', 'type' => 'stress', 'version' => 1]);
        $b = Questionnaire::query()->create(['title' => 'B', 'type' => 'stress', 'version' => 2]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $b->id, 'title' => 'B section', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.sections.edit', [$a, $section]))
            ->assertNotFound();
    }

    public function test_the_sections_page_is_the_full_questionnaire_editor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Emotional', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.sections.index', $questionnaire))
            ->assertOk()
            ->assertSee('1. Details')
            ->assertSee('2. Sections &amp; questions', false)
            ->assertSee('3. Result ranges')
            ->assertSee(route('admin.questionnaires.details', $questionnaire), false)
            ->assertSee(route('admin.questionnaires.ranges', $questionnaire), false)
            ->assertSee('Emotional');
    }

    public function test_details_form_updates_labelling_and_status_without_touching_questions_or_ranges(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Old title', 'type' => 'stress', 'version' => 1, 'status' => 'draft']);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'S', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);
        $question = StressQuestion::query()->create(['question_text' => 'Q', 'question_type' => 'scale', 'is_active' => true]);
        $questionnaire->questions()->attach($question->id, ['position' => 1, 'is_required' => true, 'questionnaire_section_id' => $section->id]);
        $band = $questionnaire->scoreBands()->create(['code' => 'a', 'label' => 'A', 'min_score' => 0, 'max_score' => 5, 'scope' => 'overall', 'is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.questionnaires.details', $questionnaire), [
                'title' => 'New title', 'description' => 'desc', 'period' => 'S1', 'version' => 2, 'status' => 'draft',
            ])
            ->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));

        $questionnaire->refresh();
        $this->assertSame('New title', $questionnaire->title);
        $this->assertSame(2, $questionnaire->version);
        // Questions and ranges untouched.
        $this->assertSame(1, $questionnaire->questions()->count());
        $this->assertDatabaseHas('stress_score_bands', ['id' => $band->id, 'is_active' => true]);
    }

    public function test_ranges_form_updates_bands_without_touching_questions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'S', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);
        $question = StressQuestion::query()->create(['question_text' => 'Q', 'question_type' => 'scale', 'is_active' => true]);
        $questionnaire->questions()->attach($question->id, ['position' => 1, 'is_required' => true, 'questionnaire_section_id' => $section->id]);

        $this->actingAs($admin)
            ->patch(route('admin.questionnaires.ranges', $questionnaire), [
                'bands' => [
                    ['scope' => 'overall', 'code' => 'lo', 'label' => 'Low', 'min_score' => 0, 'max_score' => 2, 'position' => 1, 'is_active' => '1'],
                    ['scope' => 'overall', 'code' => 'hi', 'label' => 'High', 'min_score' => 3, 'max_score' => 5, 'position' => 2, 'is_active' => '1'],
                ],
            ])
            ->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));

        $this->assertSame(2, $questionnaire->scoreBands()->where('is_active', true)->count());
        $this->assertSame(1, $questionnaire->questions()->count());
    }

    public function test_ranges_form_rejects_overlapping_bands(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);

        $this->actingAs($admin)
            ->from(route('admin.questionnaires.sections.index', $questionnaire))
            ->patch(route('admin.questionnaires.ranges', $questionnaire), [
                'bands' => [
                    ['scope' => 'overall', 'code' => 'a', 'label' => 'A', 'min_score' => 0, 'max_score' => 5, 'position' => 1, 'is_active' => '1'],
                    ['scope' => 'overall', 'code' => 'b', 'label' => 'B', 'min_score' => 4, 'max_score' => 9, 'position' => 2, 'is_active' => '1'],
                ],
            ])
            ->assertSessionHasErrors();
    }

    public function test_non_admins_cannot_manage_sections(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);

        $this->actingAs($student)
            ->get(route('admin.questionnaires.sections.index', $questionnaire))
            ->assertForbidden();
    }

    public function test_admin_can_add_edit_and_delete_a_question_inside_a_section(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Emotional', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);

        // Add
        $this->actingAs($admin)
            ->post(route('admin.questionnaires.sections.questions.store', [$questionnaire, $section]), [
                'question_text' => 'I feel calm most days.',
                'question_type' => 'scale',
                'is_active' => '1',
                'is_required' => '1',
                'wellbeing_weight' => 2,
                'options' => [
                    ['label' => 'No', 'value' => 'no', 'score' => 1],
                    ['label' => 'Yes', 'value' => 'yes', 'score' => 5],
                ],
            ])
            ->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));

        $question = $questionnaire->questions()->firstOrFail();
        $this->assertSame('I feel calm most days.', $question->question_text);
        $this->assertSame((int) $section->id, (int) $question->pivot->questionnaire_section_id);
        $this->assertTrue((bool) $question->pivot->is_required);
        $this->assertEqualsWithDelta(2.0, (float) $question->wellbeing_weight, 0.001);

        // The section index lists it
        $this->actingAs($admin)
            ->get(route('admin.questionnaires.sections.index', $questionnaire))
            ->assertOk()
            ->assertSee('I feel calm most days.');

        // Edit — resubmit the existing options with their ids, as the form does
        $existingOptions = $question->options()->orderBy('id')->get(['id', 'value']);
        $this->actingAs($admin)
            ->put(route('admin.questionnaires.sections.questions.update', [$questionnaire, $section, $question]), [
                'question_text' => 'I feel calm and steady.',
                'question_type' => 'scale',
                'is_active' => '1',
                'is_required' => '1',
                'options' => [
                    ['id' => $existingOptions[0]->id, 'label' => 'Not really', 'value' => $existingOptions[0]->value, 'score' => 1],
                    ['id' => $existingOptions[1]->id, 'label' => 'Yes', 'value' => $existingOptions[1]->value, 'score' => 5],
                ],
            ])
            ->assertRedirect(route('admin.questionnaires.sections.index', $questionnaire));

        $this->assertSame('I feel calm and steady.', $question->fresh()->question_text);

        // Delete (no history) -> removed entirely
        $this->actingAs($admin)
            ->delete(route('admin.questionnaires.sections.questions.destroy', [$questionnaire, $section, $question]))
            ->assertRedirect();

        $this->assertSame(0, $questionnaire->questions()->count());
        $this->assertDatabaseMissing('stress_questions', ['id' => $question->id]);
    }

    public function test_a_question_can_have_any_number_of_answer_options_and_the_count_can_change(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $section = QuestionnaireSection::query()->create([
            'questionnaire_id' => $questionnaire->id, 'title' => 'Scale', 'position' => 1, 'category_weight' => 5, 'is_active' => true,
        ]);

        // The add form offers dynamic option rows.
        $this->actingAs($admin)
            ->get(route('admin.questionnaires.sections.questions.create', [$questionnaire, $section]))
            ->assertOk()
            ->assertSee('Add answer option')
            ->assertSee('add-option', false);

        // Create with a 3-point scale.
        $this->actingAs($admin)->post(route('admin.questionnaires.sections.questions.store', [$questionnaire, $section]), [
            'question_text' => 'Rate it', 'question_type' => 'scale', 'is_active' => '1', 'is_required' => '1',
            'options' => [
                ['label' => 'Low', 'value' => 'low', 'score' => 1],
                ['label' => 'Mid', 'value' => 'mid', 'score' => 2],
                ['label' => 'High', 'value' => 'high', 'score' => 3],
            ],
        ])->assertRedirect();

        $question = $questionnaire->questions()->firstOrFail();
        $this->assertSame(3, $question->options()->where('is_active', true)->count());

        // Grow it to a 7-point scale (keep the first three, add four more).
        $keep = $question->options()->orderBy('id')->get();
        $payload = $keep->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'value' => $o->value, 'score' => $o->score])->all();
        for ($n = 4; $n <= 7; $n++) {
            $payload[] = ['label' => "Point {$n}", 'value' => "p{$n}", 'score' => $n];
        }
        $this->actingAs($admin)->put(route('admin.questionnaires.sections.questions.update', [$questionnaire, $section, $question]), [
            'question_text' => 'Rate it', 'question_type' => 'scale', 'is_active' => '1', 'is_required' => '1',
            'options' => $payload,
        ])->assertRedirect();

        $this->assertSame(7, $question->options()->where('is_active', true)->count());

        // Below two options is rejected.
        $this->actingAs($admin)
            ->from(route('admin.questionnaires.sections.questions.edit', [$questionnaire, $section, $question]))
            ->put(route('admin.questionnaires.sections.questions.update', [$questionnaire, $section, $question]), [
                'question_text' => 'Rate it', 'question_type' => 'scale', 'is_active' => '1',
                'options' => [['label' => 'Only one', 'value' => 'one', 'score' => 1]],
            ])
            ->assertSessionHasErrors('options');
    }

    public function test_a_question_from_another_section_cannot_be_edited_through_this_one(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);
        $a = QuestionnaireSection::query()->create(['questionnaire_id' => $questionnaire->id, 'title' => 'A', 'position' => 1, 'category_weight' => 5, 'is_active' => true]);
        $b = QuestionnaireSection::query()->create(['questionnaire_id' => $questionnaire->id, 'title' => 'B', 'position' => 2, 'category_weight' => 5, 'is_active' => true]);

        $question = StressQuestion::query()->create(['question_text' => 'In A', 'question_type' => 'scale', 'is_active' => true]);
        $question->options()->createMany([
            ['label' => 'No', 'value' => 'no', 'score' => 1, 'position' => 1, 'is_active' => true],
            ['label' => 'Yes', 'value' => 'yes', 'score' => 5, 'position' => 2, 'is_active' => true],
        ]);
        $questionnaire->questions()->attach($question->id, [
            'position' => 1, 'is_required' => true, 'questionnaire_section_id' => $a->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.sections.questions.edit', [$questionnaire, $b, $question]))
            ->assertNotFound();

        // The scoped add / edit forms render and are anchored to this section.
        $this->actingAs($admin)
            ->get(route('admin.questionnaires.sections.questions.create', [$questionnaire, $a]))
            ->assertOk()
            ->assertSee('· A')
            ->assertSee('Required in this questionnaire');

        $this->actingAs($admin)
            ->get(route('admin.questionnaires.sections.questions.edit', [$questionnaire, $a, $question]))
            ->assertOk()
            ->assertSee('In A');
    }
}
