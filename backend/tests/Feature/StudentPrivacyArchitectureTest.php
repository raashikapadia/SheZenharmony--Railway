<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\CrisisReport;
use App\Models\Intervention;
use App\Models\InterventionUsage;
use App\Models\ProgressEntry;
use App\Models\StressAssessment;
use App\Models\StudentConsent;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\EmailOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentPrivacyArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_a_usp_student_email(): void
    {
        $email = 's12345678@student.usp.ac.fj';
        $this->postJson('/api/v1/auth/register', $this->registrationPayload($email))
            ->assertCreated();
        $this->assertDatabaseHas('users', ['email' => $email]);

        foreach (['student@usp.ac.fj', 'student@gmail.com', 'not-an-email'] as $invalidEmail) {
            $this->postJson('/api/v1/auth/register', $this->registrationPayload($invalidEmail))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');
        }
    }

    public function test_registration_requires_and_records_pseudonymous_privacy_consent(): void
    {
        $withoutConsent = $this->registrationPayload('s12340001@student.usp.ac.fj');
        unset($withoutConsent['privacy_consent']);

        $this->postJson('/api/v1/auth/register', $withoutConsent)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('privacy_consent');
        $this->assertDatabaseCount('users', 0);

        $this->postJson(
            '/api/v1/auth/register',
            $this->registrationPayload('s12340001@student.usp.ac.fj')
        )->assertCreated();

        $student = User::query()->where('email', 's12340001@student.usp.ac.fj')->firstOrFail();
        $consent = $student->studentIdentity->consents()->sole();
        $this->assertSame(StudentConsent::CURRENT_POLICY_VERSION, $consent->policy_version);
        $this->assertNotNull($consent->accepted_at);
        $this->assertFalse(Schema::hasColumn('student_consents', 'email'));
        $this->assertFalse(Schema::hasColumn('student_consents', 'name'));
        $this->assertFalse(Schema::hasColumn('student_consents', 'student_id'));
    }

    public function test_pseudonymous_identity_is_stable_and_never_exposed_to_mobile(): void
    {
        Notification::fake();
        $student = User::factory()->create([
            'email' => 's10000001@student.usp.ac.fj',
            'password' => 'student-password',
            'role' => User::ROLE_STUDENT,
        ]);
        $uuid = $student->studentIdentity->pseudonymous_uuid;

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'first device',
        ])->assertOk()->assertJsonMissingPath('token')->assertJsonMissingPath('user.email');

        $firstToken = $this->verifyLogin($student, $first->json('mfa.challenge_id'));

        $this->withToken($firstToken)->postJson('/api/v1/auth/logout')->assertOk();

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'second device',
        ])->assertOk()->assertJsonMissingPath('token');

        $secondToken = $this->verifyLogin($student, $second->json('mfa.challenge_id'));

        $this->withToken($secondToken)->getJson('/api/v1/auth/me')
            ->assertOk()->assertJsonMissingPath('user.id')->assertJsonMissingPath('user.name')
            ->assertJsonMissingPath('user.email')->assertJsonMissingPath('user.pseudonymous_uuid');

        $this->assertSame($uuid, $student->fresh()->studentIdentity->pseudonymous_uuid);
        $this->assertDatabaseCount('student_identities', 1);
    }

    private function verifyLogin(User $student, string $challengeId): string
    {
        $notification = Notification::sent($student, EmailOtpNotification::class)->last();
        $this->assertNotNull($notification);

        return $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $challengeId,
            'code' => $notification->code,
        ])->assertOk()
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.pseudonymous_uuid')
            ->json('token');
    }

    public function test_demographics_and_wellbeing_records_use_student_identity(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $identity = $student->studentIdentity;
        $profile = UserProfile::query()->create([
            'user_id' => $student->id,
            'student_identity_id' => $identity->id,
            'country' => 'Fiji',
            'preferred_language' => 'English',
        ]);
        $assessment = StressAssessment::query()->create([
            'student_identity_id' => $identity->id,
            'assessment_status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->assertSame($identity->id, $profile->studentIdentity->id);
        $this->assertSame($identity->id, $assessment->studentIdentity->id);
        $this->assertNull($assessment->user_id);
    }

    public function test_admin_dashboard_remains_aggregate_and_does_not_reveal_student_identity(): void
    {
        $student = User::factory()->create([
            'name' => 'Private Student Name',
            'email' => 's10000002@student.usp.ac.fj',
            'role' => User::ROLE_STUDENT,
        ]);
        StressAssessment::query()->create([
            'student_identity_id' => $student->studentIdentity->id,
            'assessment_status' => 'completed',
            'stress_level' => 'Private tier',
            'completed_at' => now(),
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertDontSee($student->email)
            ->assertDontSee($student->name)
            ->assertDontSee($student->pseudonymous_uuid)
            ->assertDontSee('Private tier');
    }

    public function test_account_deletion_removes_mapping_demographics_and_individual_wellbeing_data(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $identity = $student->studentIdentity;
        $identity->consents()->create([
            'policy_version' => 'test-policy',
            'accepted_at' => now(),
        ]);
        UserProfile::query()->create([
            'user_id' => $student->id,
            'student_identity_id' => $identity->id,
            'country' => 'Fiji',
        ]);
        $assessment = StressAssessment::query()->create(['student_identity_id' => $identity->id]);
        $intervention = Intervention::query()->create(['title' => 'Pause', 'content_type' => 'activity']);
        $usage = InterventionUsage::query()->create([
            'student_identity_id' => $identity->id,
            'intervention_id' => $intervention->id,
        ]);
        ProgressEntry::query()->create([
            'student_identity_id' => $identity->id,
            'stress_assessment_id' => $assessment->id,
            'intervention_usage_id' => $usage->id,
            'metric_type' => 'check_in',
            'recorded_at' => now(),
        ]);
        $chat = ChatSession::query()->create(['student_identity_id' => $identity->id]);
        CrisisReport::query()->create([
            'chat_session_id' => $chat->id,
            'report_status' => 'open',
            'reported_at' => now(),
        ]);

        $this->actingAs($student)->deleteJson('/api/v1/auth/account')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseMissing('student_identities', ['id' => $identity->id]);
        $this->assertDatabaseCount('user_profiles', 0);
        $this->assertDatabaseCount('student_consents', 0);
        $this->assertDatabaseCount('stress_assessments', 0);
        $this->assertDatabaseCount('intervention_usages', 0);
        $this->assertDatabaseCount('progress_entries', 0);
        $this->assertDatabaseCount('chat_sessions', 0);
        $this->assertDatabaseCount('crisis_reports', 0);
    }

    private function registrationPayload(string $email): array
    {
        return [
            'email' => $email,
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
            'device_name' => 'test device',
            'privacy_consent' => true,
            'demographics' => [
                'date_of_birth' => '2004-03-15',
                'year_of_study' => 'Year 3',
                'gender' => 'Woman',
                'country' => 'Fiji',
                'employment_status' => 'Student',
                'relationship_status' => 'Single',
                'has_children' => false,
                'living_situation' => 'With family',
                'preferred_language' => 'English',
            ],
        ];
    }
}
