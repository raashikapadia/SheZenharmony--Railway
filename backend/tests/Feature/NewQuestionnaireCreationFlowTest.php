<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Building a questionnaire is the same five screens for a brand-new draft
 * and for an existing questionnaire:
 *
 *   1 Basic info → 2 Sections & questions → 3 Scoring → 4 Result levels → 5 Review & publish
 *
 * Every step saves in place; "Save & continue" moves to the next one.
 */
class NewQuestionnaireCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_result_levels_save_continues_to_review_or_stays_on_the_step(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->post(route('admin.questionnaires.store'), [
            'title' => 'New wellbeing check', 'result_scale_min' => 0, 'result_scale_max' => 40,
        ])->assertRedirect();
        $created = Questionnaire::query()->firstOrFail();

        $range = [
            'scope' => 'overall', 'code' => 'low', 'label' => 'Low',
            'min_score' => 0, 'max_score' => 40, 'position' => 1, 'is_active' => 1,
        ];
        $this->patch(route('admin.questionnaires.ranges', $created), [
            'bands' => [$range],
            'next' => 'review',
        ])->assertRedirect(route('admin.questionnaires.review', $created));
        $this->assertSame(1, $created->scoreBands()->count());

        $this->patch(route('admin.questionnaires.ranges', $created), [
            'bands' => [$range + ['id' => $created->scoreBands()->firstOrFail()->id]],
        ])->assertRedirect(route('admin.questionnaires.result-levels', $created));
    }

    public function test_every_questionnaire_follows_the_same_five_steps(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('admin.questionnaires.create'))
            ->assertOk()
            ->assertSee('name="title"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="purpose"', false)
            ->assertSee('name="estimated_minutes"', false)
            ->assertSee('name="result_basis"', false)
            ->assertSee('name="published_at"', false)
            ->assertSee('name="result_scale_min"', false)
            ->assertSee('name="result_scale_max"', false);

        // Percentage results need no scale typed in: the engine reports 0–100.
        $createResponse = $this->post(route('admin.questionnaires.store'), [
            'title' => 'New wellbeing check',
            'estimated_minutes' => 8,
            'result_basis' => 'percentage',
        ]);

        $created = Questionnaire::query()->where('title', 'New wellbeing check')->firstOrFail();
        $this->assertSame('draft', $created->status);
        $this->assertFalse((bool) $created->is_active);
        $this->assertSame(Questionnaire::PURPOSE_LIBRARY, $created->purpose);
        $this->assertSame(8, $created->estimated_minutes);
        $this->assertSame([0, 100], $created->resultScale());
        $this->assertSame(Questionnaire::SCORING_WEIGHTED_SECTIONS, $created->scoringMethod());
        $this->assertTrue($created->usesEqualSectionWeights());
        $createResponse->assertRedirect(route('admin.questionnaires.show-details', $created));

        $this->get(route('admin.questionnaires.show-details', $created))
            ->assertOk()
            ->assertSee('Step 1 of 5')
            ->assertSee('Basic information')
            ->assertSee('continue to Sections &amp; Questions', false)
            ->assertDontSee(route('admin.questionnaires.ranges', $created), false);

        $this->patch(route('admin.questionnaires.details', $created), [
            'title' => $created->title,
            'description' => '',
            'version' => $created->version,
            'status' => 'draft',
            'next' => 'sections',
        ])->assertRedirect(route('admin.questionnaires.sections.index', $created));

        $this->get(route('admin.questionnaires.sections.index', $created))
            ->assertOk()
            ->assertSee('Step 2 of 5')
            ->assertSee('Continue to Scoring');

        $this->get(route('admin.questionnaires.scoring', $created))
            ->assertOk()
            ->assertSee('Step 3 of 5')
            ->assertSee('Weighted sections')
            ->assertSee('Points total')
            ->assertSee('Assessment overview')
            ->assertSee('continue to Result levels', false);

        $this->patch(route('admin.questionnaires.scoring.update', $created), [
            'scoring_method' => 'points_total',
            'section_weighting' => 'custom',
            'result_basis' => 'scale',
            'result_scale_min' => 0,
            'result_scale_max' => 40,
            'next' => 'results',
        ])->assertRedirect(route('admin.questionnaires.result-levels', $created));
        $created->refresh();
        $this->assertSame(Questionnaire::SCORING_POINTS_TOTAL, $created->scoringMethod());
        $this->assertFalse($created->usesEqualSectionWeights());
        $this->assertSame([0, 40], $created->resultScale());

        $this->get(route('admin.questionnaires.result-levels', $created))
            ->assertOk()
            ->assertSee('Step 4 of 5')
            ->assertSee('What each result means')
            ->assertSee('continue to Review &amp; Publish', false);

        $this->get(route('admin.questionnaires.review', $created))
            ->assertOk()
            ->assertSee('Step 5 of 5')
            ->assertSee(route('admin.questionnaires.result-levels', $created).'#ranges')
            ->assertSee('Not ready to publish yet');

        // An existing questionnaire opens on exactly the same steps.
        $existing = Questionnaire::query()->create(['title' => 'Existing', 'type' => 'stress', 'version' => 1]);
        $this->get(route('admin.questionnaires.show-details', $existing))->assertOk()->assertSee('Step 1 of 5');
        $this->get(route('admin.questionnaires.scoring', $existing))->assertOk()->assertSee('Step 3 of 5');
        $this->get(route('admin.questionnaires.result-levels', $existing))->assertOk()->assertSee('Step 4 of 5');
    }
}
