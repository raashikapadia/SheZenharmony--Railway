<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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

    public function test_invalid_or_inactive_admin_cannot_sign_in(): void
    {
        $admin = User::factory()->create([
            'password' => 'admin-password',
            'role' => User::ROLE_ADMIN,
            'account_status' => 'suspended',
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'admin-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_logout_invalidates_session_and_protects_navigation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/questionnaires')->assertOk();
        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->get('/admin/questionnaires')->assertRedirect(route('admin.login'));
    }

    public function test_student_cannot_view_admin_dashboard(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($student)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_registered_students_are_visible_only_by_pseudonymous_identity(): void
    {
        $student = User::factory()->create([
            'name' => 'Hidden Student Name',
            'email' => 'hidden@student.usp.ac.fj',
            'role' => User::ROLE_STUDENT,
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/students')
            ->assertOk()
            ->assertSee($student->studentIdentity->displayId())
            ->assertDontSee($student->name)
            ->assertDontSee($student->email)
            ->assertDontSee('Language');

        $this->actingAs($student)->get('/admin/students')->assertForbidden();
        Auth::logout();
        $this->get('/admin/students')->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_view_and_edit_student_demographics_without_seeing_identity(): void
    {
        $student = User::factory()->create([
            'name' => 'Hidden Student Name',
            'email' => 'hidden2@student.usp.ac.fj',
            'role' => User::ROLE_STUDENT,
        ]);
        $identity = $student->studentIdentity;
        $identity->profile()->create([
            'date_of_birth' => '2000-01-01',
            'year_of_study' => 'Year 2',
            'country' => 'Fiji',
            'employment_status' => 'Not employed',
            'relationship_status' => 'Single',
            'has_children' => false,
            'living_situation' => 'With family',
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get("/admin/students/{$identity->id}")
            ->assertOk()
            ->assertSee($identity->displayId())
            ->assertDontSee($student->name)
            ->assertDontSee($student->email);

        $this->actingAs($admin)->put("/admin/students/{$identity->id}", [
            'date_of_birth' => '2000-01-01',
            'country' => 'Samoa',
            'year_of_study' => 'Year 3',
            'employment_status' => 'Part-time',
            'relationship_status' => 'Single',
            'has_children' => '0',
            'living_situation' => 'Living alone',
        ])->assertRedirect(route('admin.students.show', $identity));

        $this->assertDatabaseHas('user_profiles', [
            'student_identity_id' => $identity->id,
            'country' => 'Samoa',
            'year_of_study' => 'Year 3',
            'living_situation' => 'Living alone',
        ]);
        $this->assertSame('active', $student->fresh()->account_status);
    }

    public function test_admin_cannot_directly_edit_calculated_age_and_non_admin_cannot_reach_student_routes(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $identity = $student->studentIdentity;
        $identity->profile()->create(['date_of_birth' => '2000-01-01']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $expectedAge = Carbon::parse('2000-01-01')->age;

        $this->actingAs($admin)->put("/admin/students/{$identity->id}", [
            'date_of_birth' => '2000-01-01',
            'age' => 999,
        ])->assertRedirect(route('admin.students.show', $identity));

        $this->assertSame($expectedAge, $identity->fresh()->profile->age);
        $this->assertNotEquals(999, $identity->fresh()->profile->age);

        $this->actingAs($student)->get("/admin/students/{$identity->id}")->assertForbidden();
        $this->actingAs($student)->get("/admin/students/{$identity->id}/edit")->assertForbidden();
        $this->actingAs($student)->put("/admin/students/{$identity->id}", [])->assertForbidden();

        Auth::logout();
        $this->get("/admin/students/{$identity->id}")->assertRedirect(route('admin.login'));
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
