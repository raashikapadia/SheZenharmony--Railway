<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_changes_to_questionnaire_configuration_are_recorded(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $questionnaire = Questionnaire::query()->create(['title' => 'Wellbeing', 'type' => 'stress', 'version' => 1]);

        $this->actingAs($admin)->post(route('admin.questionnaires.sections.store', $questionnaire), [
            'title' => 'Emotional', 'category_weight' => 5, 'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.questions.store'), [
            'question_text' => 'I feel calm.',
            'question_type' => 'scale',
            'position' => 1,
            'is_active' => '1',
            'wellbeing_weight' => 2,
            'options' => [
                ['label' => 'No', 'value' => 'no', 'score' => 1],
                ['label' => 'Yes', 'value' => 'yes', 'score' => 5],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('questionnaire_audit_logs', [
            'questionnaire_id' => $questionnaire->id,
            'action' => 'section.created',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('questionnaire_audit_logs', [
            'action' => 'question.created',
            'user_id' => $admin->id,
        ]);
    }
}
