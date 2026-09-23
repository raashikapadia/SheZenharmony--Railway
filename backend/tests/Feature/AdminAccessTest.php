<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\User;
use App\Models\WellbeingActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_page_uses_the_split_visual_layout_without_changing_form_fields(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('admin-login-shell', false)
            ->assertSee('admin-login-floral-logo', false)
            ->assertSee('images/admin-login-floral-logo.png', false)
            ->assertSee('admin-login-illustration', false)
            ->assertSee('images/admin-login-office.png', false)
            ->assertDontSee('Forgot your password?')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false);
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

    public function test_dashboard_counts_only_completed_assessments_and_labels_all_students(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $activeStudent = User::factory()->create(['role' => User::ROLE_STUDENT, 'account_status' => 'active']);
        $heldStudent = User::factory()->create(['role' => User::ROLE_STUDENT, 'account_status' => 'suspended']);
        StressAssessment::query()->create([
            'student_identity_id' => $activeStudent->studentIdentity->id,
            'assessment_status' => 'completed',
        ]);
        StressAssessment::query()->create([
            'student_identity_id' => $heldStudent->studentIdentity->id,
            'assessment_status' => 'in_progress',
        ]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertViewHas('studentCount', 2)
            ->assertViewHas('assessmentCount', 1)
            ->assertSee('Across all account statuses');
    }

    public function test_admin_sidebar_uses_the_reorganised_sections_and_placeholder_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSeeInOrder([
                'Assessment',
                'Questionnaire Management',
                'Wellbeing Activities',
                'Support Content',
                'Video Activities',
                'Personal Guidance',
                'Positive Engagement',
                'Shezen Chat Buddy',
                'Users',
                'Registered Students',
                'Demographic Reports',
            ])
            ->assertSee('Assessments')
            ->assertDontSee('Categories');

        foreach ([
            'admin.interventions.index' => 'Support Content',
            'admin.wellbeing_activities.index' => 'Video Activities',
            'admin.personal-guidance.index' => 'Personal Guidance',
        ] as $routeName => $label) {
            $this->actingAs($admin)->get(route($routeName))
                ->assertOk()
                ->assertSee($label)
                ->assertSee(
                    'class="side-link active" href="'.route($routeName).'"',
                    false,
                );
        }

        $this->actingAs($admin)->get(route('admin.positive-engagement.index'))
            ->assertOk()
            ->assertSee('Positive Engagement')
            ->assertSee('Add positive engagement item')
            ->assertSee(
                'class="side-link active" href="'.route('admin.positive-engagement.index').'"',
                false,
            );

        // Shezen's rule-based conversation is managed through the release manager.
        $this->actingAs($admin)->get(route('admin.chatbuddy.releases.index'))
            ->assertOk()
            ->assertSee('Chat Buddy Manager')
            ->assertSee('Publish draft')
            ->assertSee(
                'class="side-link active" href="'.route('admin.chatbuddy.releases.index').'"',
                false,
            );
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

    public function test_admin_can_view_student_demographics_read_only_without_seeing_identity(): void
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

        $this->actingAs($admin)
            ->get("/admin/students/{$identity->id}/edit")
            ->assertNotFound();
        $this->actingAs($admin)
            ->put("/admin/students/{$identity->id}", ['country' => 'Samoa'])
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('user_profiles', [
            'student_identity_id' => $identity->id,
            'country' => 'Fiji',
            'year_of_study' => 'Year 2',
            'living_situation' => 'With family',
        ]);
        $this->assertSame('active', $student->fresh()->account_status);
    }

    public function test_non_admin_cannot_reach_student_management_routes(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $identity = $student->studentIdentity;

        $this->actingAs($student)->get("/admin/students/{$identity->id}")->assertForbidden();
        $this->actingAs($student)->patch("/admin/students/{$identity->id}/hold", [
            'reason' => 'Repeated violation of the community rules.',
        ])->assertForbidden();
        $this->actingAs($student)->patch("/admin/students/{$identity->id}/reactivate")->assertForbidden();

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
            'title' => 'Guided journal reflection',
            'description' => 'Write about one helpful moment.',
            'content_type' => 'journaling',
            'stress_level' => 'high',
            'is_active' => '1',
        ])->assertRedirect(route('admin.interventions.index'));

        $this->assertDatabaseHas('interventions', [
            'title' => 'Guided journal reflection',
            'content_type' => 'journaling',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_make_a_video_activity_inactive(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $activity = WellbeingActivity::query()->create([
            'title' => 'A short reset',
            'video_url' => 'https://www.youtube.com/watch?v=example',
            'video_type' => 'youtube',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.wellbeing_activities.update', $activity), [
            'title' => 'A short reset',
            'video_url' => 'https://www.youtube.com/watch?v=example',
            'video_type' => 'youtube',
        ])->assertRedirect(route('admin.wellbeing_activities.index'));

        $this->assertFalse($activity->fresh()->is_active);
        $this->getJson('/api/v1/wellbeing-activities')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_support_content_admin_is_limited_to_journaling(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $existingAffirmation = Intervention::query()->create([
            'title' => 'Existing affirmation',
            'content_type' => 'affirmation',
            'is_active' => true,
        ]);
        Intervention::query()->create([
            'title' => 'Journal prompt',
            'content_type' => 'journaling',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.interventions.index'))
            ->assertOk()
            ->assertSee('Journal prompt')
            ->assertDontSee('Existing affirmation');

        $this->actingAs($admin)->get(route('admin.interventions.create'))
            ->assertOk()
            ->assertSee('<option value="journaling"', false)
            ->assertDontSee('value="affirmation"', false)
            ->assertDontSee('value="quiz"', false)
            ->assertDontSee('value="motivation"', false);

        $this->actingAs($admin)->post('/admin/interventions', [
            'title' => 'Rejected affirmation',
            'content_type' => 'affirmation',
            'is_active' => '1',
        ])->assertSessionHasErrors('content_type');

        $this->actingAs($admin)
            ->get(route('admin.interventions.edit', $existingAffirmation))
            ->assertNotFound();

        $this->assertDatabaseHas('interventions', [
            'id' => $existingAffirmation->id,
            'title' => 'Existing affirmation',
            'content_type' => 'affirmation',
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
