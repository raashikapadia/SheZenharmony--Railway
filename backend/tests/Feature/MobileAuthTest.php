<?php

namespace Tests\Feature;

use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\StudentConsent;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_login_view_profile_and_logout(): void
    {
        Notification::fake();
        $student = User::factory()->create([
            'password' => 'student-password',
            'role' => User::ROLE_STUDENT,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'test device',
        ]);

        $challengeId = $login->assertOk()
            ->assertJsonMissingPath('token')
            ->json('mfa.challenge_id');
        $token = $this->verifyChallenge($student, $challengeId);

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

    public function test_held_student_receives_an_account_hold_response(): void
    {
        $student = User::factory()->create([
            'password' => 'student-password',
            'role' => User::ROLE_STUDENT,
            'account_status' => 'suspended',
            'account_hold_reason' => 'Repeated violation of the community rules.',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'test device',
        ])->assertStatus(423)
            ->assertJsonPath('code', 'account_on_hold')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Repeated violation'));
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

    public function test_registration_activates_student_and_issues_token_only_after_otp(): void
    {
        Notification::fake();
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 's12345678@student.usp.ac.fj',
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
            'device_name' => 'test device',
            'demographics' => $this->demographics(),
            'privacy_consent' => true,
        ])->assertCreated()
            ->assertJsonMissingPath('token')
            ->assertJsonPath('mfa.purpose', 'registration');

        $student = User::query()->where('email', 's12345678@student.usp.ac.fj')->firstOrFail();
        $this->assertTrue($student->hasRole(User::ROLE_STUDENT));
        $this->assertSame('pending_verification', $student->account_status);
        $this->assertNull($student->email_verified_at);
        $this->assertNotNull($student->studentIdentity);
        $this->assertSame($student->pseudonymous_uuid, $student->studentIdentity->pseudonymous_uuid);
        $this->assertNull($student->name);
        $this->assertSame('Fiji', $student->studentIdentity->profile->country);
        $this->assertNull($student->studentIdentity->profile->preferred_language);
        $this->assertNull($student->studentIdentity->profile->user_id);
        $this->assertDatabaseHas('student_consents', [
            'student_identity_id' => $student->studentIdentity->id,
            'policy_version' => StudentConsent::CURRENT_POLICY_VERSION,
        ]);
        $verified = $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $response->json('mfa.challenge_id'),
            'code' => $this->latestCodeFor($student),
        ])->assertOk();

        $student->refresh();
        $this->assertSame('active', $student->account_status);
        $this->assertNotNull($student->email_verified_at);
        $verified->assertJsonMissingPath('user.id')
            ->assertJsonMissingPath('user.name')
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.pseudonymous_uuid')
            ->assertJsonPath('user.shezen_id', $student->studentIdentity->displayId())
            ->assertJsonPath('user.has_completed_required_assessment', false);
        $this->assertNotEmpty($verified->json('token'));
    }

    public function test_complete_registration_logout_login_and_me_lifecycle(): void
    {
        Notification::fake();
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

        $registrationToken = $this->verifyChallenge(
            $student,
            $registration->json('mfa.challenge_id')
        );

        $this->withToken($registrationToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => strtoupper($email),
            'password' => $password,
            'device_name' => 'login device',
        ])->assertOk()->assertJsonMissingPath('token');

        $loginToken = $this->verifyChallenge($student, $login->json('mfa.challenge_id'));

        $this->withToken($loginToken)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', User::ROLE_STUDENT)
            ->assertJsonPath('user.shezen_id', $student->studentIdentity->displayId())
            ->assertJsonPath('user.has_completed_required_assessment', false)
            ->assertJsonPath('user.has_current_consent', true)
            ->assertJsonPath('user.consent_version', StudentConsent::CURRENT_POLICY_VERSION);

        $student->refresh();
        $this->assertSame($identityId, $student->studentIdentity->id);
        $this->assertSame($uuid, $student->studentIdentity->pseudonymous_uuid);
        $this->assertDatabaseCount('student_identities', 1);
    }


    public function test_existing_student_without_current_consent_is_flagged_and_can_consent_again(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $identity = $student->studentIdentity;
        // Registered under earlier wording: that acceptance no longer counts.
        $identity->consents()->create([
            'policy_version' => 'shezen-privacy-notice-v1-draft',
            'accepted_at' => now()->subMonth(),
        ]);
        $token = $student->createToken('consent test', ['student'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.has_current_consent', false);

        $this->withToken($token)
            ->postJson('/api/v1/auth/consent', ['privacy_consent' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('privacy_consent');
        $this->assertFalse($identity->fresh()->hasCurrentConsent());

        $this->withToken($token)
            ->postJson('/api/v1/auth/consent', ['privacy_consent' => true])
            ->assertOk()
            ->assertJsonPath('user.has_current_consent', true);
        $this->assertDatabaseHas('student_consents', [
            'student_identity_id' => $identity->id,
            'policy_version' => StudentConsent::CURRENT_POLICY_VERSION,
        ]);

        // Consenting twice keeps a single row per wording version.
        $this->withToken($token)
            ->postJson('/api/v1/auth/consent', ['privacy_consent' => true])
            ->assertOk();
        $this->assertSame(2, $identity->consents()->count());
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.has_current_consent', true);
    }

    public function test_declining_consent_deletes_the_account_through_the_existing_endpoint(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $identity = $student->studentIdentity;
        $identity->consents()->create([
            'policy_version' => 'shezen-privacy-notice-v1-draft',
            'accepted_at' => now()->subMonth(),
        ]);
        $token = $student->createToken('decline test', ['student'])->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/auth/account')->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseMissing('student_consents', ['student_identity_id' => $identity->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_student_can_permanently_delete_account_and_associated_data(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $identity = $student->studentIdentity;
        $identity->profile()->create(['country' => 'Fiji']);
        $questionnaire = Questionnaire::query()->create([
            'title' => 'Deletion test',
            'type' => 'stress',
            'version' => 1,
            'status' => 'published',
            'is_active' => true,
        ]);
        StressAssessment::query()->create([
            'student_identity_id' => $identity->id,
            'questionnaire_id' => $questionnaire->id,
            'assessment_type' => 'stress',
            'assessment_status' => 'completed',
            'total_score' => 1,
            'completed_at' => now(),
        ]);
        $token = $student->createToken('deletion test', ['student'])->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/account')
            ->assertOk()
            ->assertJsonPath('message', 'Account deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $student->id]);
        $this->assertDatabaseMissing('student_identities', ['id' => $identity->id]);
        $this->assertDatabaseMissing('user_profiles', ['student_identity_id' => $identity->id]);
        $this->assertDatabaseMissing('stress_assessments', ['student_identity_id' => $identity->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_rejects_participants_who_are_not_yet_eighteen(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-09-18 10:00:00'));
        $payload = fn (string $dateOfBirth, string $email) => [
            'email' => $email,
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
            'device_name' => 'test device',
            'demographics' => array_merge($this->demographics(), ['date_of_birth' => $dateOfBirth]),
            'privacy_consent' => true,
        ];

        // A 2008 birth year is not enough on its own: this birthday is tomorrow.
        $this->postJson('/api/v1/auth/register', $payload('2008-09-19', 's10000001@student.usp.ac.fj'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'demographics.date_of_birth' => 'You must be 18 years or older to participate.',
            ]);
        $this->postJson('/api/v1/auth/register', $payload('2010-05-05', 's10000002@student.usp.ac.fj'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('demographics.date_of_birth');
        $this->assertDatabaseCount('users', 0);

        // Eighteen today is old enough.
        $this->postJson('/api/v1/auth/register', $payload('2008-09-18', 's10000003@student.usp.ac.fj'))
            ->assertCreated();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_with_other_year_of_study_requires_a_specification(): void
    {
        Notification::fake();
        $base = [
            'email' => 's20000001@student.usp.ac.fj',
            'password' => 'safe-password',
            'password_confirmation' => 'safe-password',
            'device_name' => 'test device',
            'privacy_consent' => true,
        ];

        $this->postJson('/api/v1/auth/register', $base + [
            'demographics' => array_merge($this->demographics(), ['year_of_study' => 'Other']),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'demographics.year_of_study_detail' => 'Please specify your year of study.',
            ]);

        $this->postJson('/api/v1/auth/register', $base + [
            'demographics' => array_merge($this->demographics(), [
                'year_of_study' => 'Other',
                'year_of_study_detail' => 'Foundation programme',
            ]),
        ])->assertCreated();
        $this->assertDatabaseHas('user_profiles', [
            'year_of_study' => 'Other',
            'year_of_study_detail' => 'Foundation programme',
        ]);

        // The detail is meaningless without "Other", so it is not kept.
        $this->postJson('/api/v1/auth/register', array_merge($base, [
            'email' => 's20000002@student.usp.ac.fj',
            'demographics' => array_merge($this->demographics(), [
                'year_of_study' => 'Year 1',
                'year_of_study_detail' => 'Should be dropped',
            ]),
        ]))->assertCreated();
        $this->assertDatabaseHas('user_profiles', ['year_of_study' => 'Year 1', 'year_of_study_detail' => null]);
        $this->assertDatabaseMissing('user_profiles', ['year_of_study_detail' => 'Should be dropped']);
    }

    public function test_each_student_receives_a_short_unique_shezen_id_that_survives_login(): void
    {
        Notification::fake();
        $ids = [];
        foreach (['s30000001', 's30000002', 's30000003'] as $handle) {
            $this->postJson('/api/v1/auth/register', [
                'email' => "{$handle}@student.usp.ac.fj",
                'password' => 'safe-password',
                'password_confirmation' => 'safe-password',
                'device_name' => 'test device',
                'demographics' => $this->demographics(),
                'privacy_consent' => true,
            ])->assertCreated();
            $ids[] = User::query()->where('email', "{$handle}@student.usp.ac.fj")->firstOrFail()
                ->studentIdentity->displayId();
        }

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^SZ[A-HJ-NP-Z2-9]{5}$/', $id);
        }
        $this->assertCount(3, array_unique($ids));

        // Signing in again on a new device returns the same ID, not a fresh one.
        $student = User::query()->where('email', 's30000001@student.usp.ac.fj')->firstOrFail();
        $student->forceFill(['account_status' => 'active'])->save();
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'safe-password',
            'device_name' => 'another device',
        ])->assertOk();
        $token = $this->verifyChallenge($student, $login->json('mfa.challenge_id'));
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.shezen_id', $ids[0]);
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

    private function verifyChallenge(User $student, string $challengeId): string
    {
        return $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $challengeId,
            'code' => $this->latestCodeFor($student),
        ])->assertOk()->json('token');
    }

    private function latestCodeFor(User $student): string
    {
        $notification = Notification::sent($student, EmailOtpNotification::class)->last();
        $this->assertNotNull($notification);

        return $notification->code;
    }
}
