<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Questionnaire;
use App\Models\StressAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_login_view_profile_and_logout(): void
    {
        $student = User::factory()->create([
            'password' => 'student-password',
            'role' => User::ROLE_STUDENT,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'test device',
        ]);

        $token = $login->assertOk()
            ->assertJsonPath('user.role', User::ROLE_STUDENT)
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $student->id);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_cannot_use_the_student_mobile_login(): void
    {
        $admin = User::factory()->create([
            'password' => 'admin-password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'admin-password',
            'device_name' => 'test device',
        ])->assertUnprocessable();
    }

    public function test_unauthorized_role_cannot_use_the_student_mobile_login(): void
    {
        $moderator = User::factory()->create([
            'password' => 'moderator-password',
            'role' => 'moderator',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $moderator->email,
            'password' => 'moderator-password',
            'device_name' => 'test device',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_inactive_student_cannot_use_the_student_mobile_login(): void
    {
        $student = User::factory()->create([
            'password' => 'student-password',
            'role' => User::ROLE_STUDENT,
            'account_status' => 'suspended',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'test device',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_invalid_mobile_credentials_fail(): void
    {
        $student = User::factory()->create([
            'password' => 'student-password',
            'role' => User::ROLE_STUDENT,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'wrong-password',
            'device_name' => 'test device',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_registration_creates_an_active_student_with_student_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Student',
            'email' => 'new.student@example.test',
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
            'device_name' => 'test device',
        ])->assertCreated()->assertJsonPath('user.role', User::ROLE_STUDENT);

        $student = User::query()->where('email', 'new.student@example.test')->firstOrFail();
        $this->assertTrue($student->hasRole(User::ROLE_STUDENT));
        $this->assertSame('active', $student->account_status);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_assessment_history_only_returns_the_authenticated_students_completed_rows(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $other = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $questionnaire = Questionnaire::query()->create([
            'title' => 'History questionnaire', 'type' => 'stress', 'version' => 1,
            'status' => 'published', 'is_active' => true,
        ]);

        StressAssessment::query()->create([
            'user_id' => $student->id, 'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'stress', 'assessment_status' => 'completed',
            'total_score' => 3, 'stress_level' => 'Low', 'completed_at' => now(),
        ]);
        StressAssessment::query()->create([
            'user_id' => $student->id, 'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'stress', 'assessment_status' => 'started',
        ]);
        StressAssessment::query()->create([
            'user_id' => $other->id, 'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'stress', 'assessment_status' => 'completed',
            'total_score' => 9, 'stress_level' => 'High', 'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->getJson('/api/v1/assessments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_score', 3);
    }
}
