<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\User;
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
            ->assertJsonPath('user.role', User::ROLE_STUDENT)
            ->assertJsonMissingPath('user.id')
            ->assertJsonMissingPath('user.name')
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.pseudonymous_uuid');

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
            'email' => 's12345678@student.usp.ac.fj',
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
            'device_name' => 'test device',
            'demographics' => $this->demographics(),
            'privacy_consent' => true,
        ])->assertCreated()->assertJsonPath('user.role', User::ROLE_STUDENT);

        $student = User::query()->where('email', 's12345678@student.usp.ac.fj')->firstOrFail();
        $this->assertTrue($student->hasRole(User::ROLE_STUDENT));
        $this->assertSame('active', $student->account_status);
        $this->assertNotNull($student->studentIdentity);
        $this->assertSame($student->pseudonymous_uuid, $student->studentIdentity->pseudonymous_uuid);
        $this->assertNull($student->name);
        $this->assertSame('Fiji', $student->studentIdentity->profile->country);
        $this->assertNull($student->studentIdentity->profile->preferred_language);
        $this->assertNull($student->studentIdentity->profile->user_id);
        $this->assertDatabaseHas('student_consents', [
            'student_identity_id' => $student->studentIdentity->id,
            'policy_version' => 'shezen-privacy-notice-v1-draft',
        ]);
        $response->assertJsonMissingPath('user.id')
            ->assertJsonMissingPath('user.name')
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.pseudonymous_uuid')
            ->assertJsonPath('user.shezen_id', $student->studentIdentity->displayId())
            ->assertJsonPath('user.has_completed_required_assessment', false);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_complete_registration_logout_login_and_me_lifecycle(): void
    {
        $email = 's87654321@student.usp.ac.fj';
        $password = 'safe-password';
        $registration = $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'device_name' => 'registration device',
            'demographics' => $this->demographics(),
            'privacy_consent' => true,
        ])->assertCreated();

        $student = User::query()->where('email', $email)->firstOrFail();
        $identityId = $student->studentIdentity->id;
        $uuid = $student->studentIdentity->pseudonymous_uuid;

        $this->withToken($registration->json('token'))
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => strtoupper($email),
            'password' => $password,
            'device_name' => 'login device',
        ])->assertOk()
            ->assertJsonPath('user.shezen_id', $student->studentIdentity->displayId())
            ->assertJsonPath('user.has_completed_required_assessment', false);

        $this->withToken($login->json('token'))->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_STUDENT)
            ->assertJsonPath('user.shezen_id', $student->studentIdentity->displayId())
            ->assertJsonPath('user.has_completed_required_assessment', false);

        $student->refresh();
        $this->assertSame($identityId, $student->studentIdentity->id);
        $this->assertSame($uuid, $student->studentIdentity->pseudonymous_uuid);
        $this->assertDatabaseCount('student_identities', 1);
    }

    public function test_duplicate_email_and_weak_password_are_validation_errors(): void
    {
        User::factory()->create(['email' => 's11111111@student.usp.ac.fj']);

        $duplicate = [
            'email' => ' S11111111@STUDENT.USP.AC.FJ ',
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
            'device_name' => 'test device',
            'demographics' => $this->demographics(),
            'privacy_consent' => true,
        ];
        $this->postJson('/api/v1/auth/register', $duplicate)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/v1/auth/register', array_merge($duplicate, [
            'email' => 's22222222@student.usp.ac.fj',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
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
            'student_identity_id' => $student->studentIdentity->id, 'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'stress', 'assessment_status' => 'completed',
            'total_score' => 3, 'stress_level' => 'Low', 'completed_at' => now(),
        ]);
        StressAssessment::query()->create([
            'student_identity_id' => $student->studentIdentity->id, 'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'stress', 'assessment_status' => 'started',
        ]);
        StressAssessment::query()->create([
            'student_identity_id' => $other->studentIdentity->id, 'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'stress', 'assessment_status' => 'completed',
            'total_score' => 9, 'stress_level' => 'High', 'completed_at' => now(),
        ]);

        $this->actingAs($student)
            ->getJson('/api/v1/assessments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.total_score', 3);
    }

    private function demographics(): array
    {
        return [
            'date_of_birth' => '2004-03-15',
            'year_of_study' => 'Year 3',
            'gender' => 'Woman',
            'country' => 'Fiji',
            'employment_status' => 'Student',
            'relationship_status' => 'Single',
            'has_children' => false,
            'living_situation' => 'With family',
        ];
    }
}
