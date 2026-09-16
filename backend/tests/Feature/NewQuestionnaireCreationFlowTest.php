<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewQuestionnaireCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_save_continues_new_draft_but_existing_save_returns_to_details(): void
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
            'result_scale_min' => 0, 'result_scale_max' => 40, 'bands' => [$range],
            'next' => 'review',
        ])->assertRedirect(route('admin.questionnaires.review', $created));
        $this->assertSame(1, $created->scoreBands()->count());

        $this->get(route('admin.questionnaires.index'))->assertOk();
        $this->patch(route('admin.questionnaires.ranges', $created), [
            'result_scale_min' => 0, 'result_scale_max' => 40,
            'bands' => [$range + ['id' => $created->scoreBands()->firstOrFail()->id]],
            'next' => 'review',
        ])->assertRedirect(route('admin.questionnaires.details', $created));
    }

    public function test_new_draft_follows_four_steps_and_existing_editing_stays_separate(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $existing = Questionnaire::query()->create(['title' => 'Existing', 'type' => 'stress', 'version' => 1]);

        $this->actingAs($admin)->get(route('admin.questionnaires.create'))
            ->assertOk()
            ->assertSee('name="title"', false)
            ->assertSee('name="description"', false)
            ->assertSee('name="published_at"', false)
            ->assertSee('name="result_scale_min"', false)
            ->assertSee('name="result_scale_max"', false);

        $createResponse = $this->post(route('admin.questionnaires.store'), [
            'title' => 'New wellbeing check',
            'result_scale_min' => 0,
            'result_scale_max' => 40,
        ]);

        $created = Questionnaire::query()->where('title', 'New wellbeing check')->firstOrFail();
        $this->assertSame('draft', $created->status);
        $this->assertFalse((bool) $created->is_active);
        $this->assertSame([0, 40], $created->resultScale());
        $createResponse->assertRedirect(route('admin.questionnaires.show-details', $created));

        $this->get(route('admin.questionnaires.show-details', $created))
            ->assertOk()
            ->assertSee('Step 1 of 4')
            ->assertSee('Continue to Sections &amp; Questions', false)
            ->assertDontSee('Result scale, ranges &amp; recommended support', false);

        $this->get(route('admin.questionnaires.show-details', $existing))
            ->assertOk()
            ->assertSee('Step 1 of 3')
            ->assertSee('Result scale, ranges &amp; recommended support', false);

        $this->patch(route('admin.questionnaires.details', $created), [
            'title' => $created->title,
            'description' => '',
            'version' => $created->version,
            'status' => 'draft',
            'next' => 'sections',
        ])->assertRedirect(route('admin.questionnaires.sections.index', $created));

        $this->get(route('admin.questionnaires.sections.index', $created))
            ->assertOk()
            ->assertSee('Step 2 of 4')
            ->assertSee('Continue to Scoring');

        $this->get(route('admin.questionnaires.scoring', $created))
            ->assertOk()
            ->assertSee('Step 3 of 4')
            ->assertSee('Save scale &amp; ranges', false)
            ->assertSee('Continue to Review &amp; Publish', false);

        $this->get(route('admin.questionnaires.review', $created))
            ->assertOk()
            ->assertSee('Step 4 of 4')
            ->assertSee(route('admin.questionnaires.scoring', $created).'#ranges')
            ->assertSee('Not ready to publish yet');

        $this->get(route('admin.questionnaires.index'))->assertOk();
        $this->get(route('admin.questionnaires.show-details', $created))
            ->assertOk()
            ->assertSee('Step 1 of 3');
    }
}
