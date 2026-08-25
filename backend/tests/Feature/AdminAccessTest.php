<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Intervention;
use App\Models\StressQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_sign_in_and_view_dashboard(): void
    {
        $admin = User::factory()->create([
            'password' => 'admin-password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'admin-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get('/admin')
            ->assertOk()
            ->assertSee('SheZen Harmony')
            ->assertSee('Administration');
    }

    public function test_student_cannot_view_admin_dashboard(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($student)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_can_manage_questionnaire_content(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post('/admin/questions', [
            'question_text' => 'How are you feeling?',
            'dimension' => 'general',
            'question_type' => 'scale',
            'position' => 1,
            'is_active' => '1',
            'options' => [
                ['label' => 'Calm', 'value' => '1', 'score' => 1],
                ['label' => 'Stressed', 'value' => '2', 'score' => 2],
            ],
        ])->assertRedirect(route('admin.questions.index'));

        $question = StressQuestion::query()->firstOrFail();
        $this->assertTrue($question->is_active);
        $this->assertCount(2, $question->options);
    }

    public function test_admin_can_manage_intervention_content(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post('/admin/interventions', [
            'title' => 'Guided breathing',
            'description' => 'Follow a short breathing exercise.',
            'content_type' => 'breathing',
            'stress_level' => 'high',
            'is_active' => '1',
        ])->assertRedirect(route('admin.interventions.index'));

        $this->assertDatabaseHas('interventions', [
            'title' => 'Guided breathing',
            'is_active' => true,
        ]);
    }

    public function test_student_cannot_manage_admin_content(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($student)->get('/admin/questions')->assertForbidden();
        $this->actingAs($student)->post('/admin/interventions', [])->assertForbidden();
        $this->assertDatabaseCount((new Intervention)->getTable(), 0);
    }
}
