<?php

namespace Tests\Feature;

use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailMfaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_valid_usp_registration_generates_hashed_otp_without_issuing_token(): void
    {
        $response = $this->registerStudent()->assertCreated()
            ->assertJsonMissingPath('token')
            ->assertJsonPath('mfa.purpose', EmailOtpChallenge::PURPOSE_REGISTRATION)
            ->assertJsonPath('mfa.masked_email', 's***@student.usp.ac.fj');

        $student = User::query()->where('email', 's12345678@student.usp.ac.fj')->sole();
        $challenge = EmailOtpChallenge::query()->findOrFail($response->json('mfa.challenge_id'));
        $code = $this->latestCodeFor($student);

        $this->assertSame('pending_verification', $student->account_status);
        $this->assertNull($student->email_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNotSame($code, $challenge->getRawOriginal('otp_hash'));
        $this->assertTrue(Hash::check($code, $challenge->otp_hash));
        Notification::assertSentTo($student, EmailOtpNotification::class);
    }

    public function test_non_usp_email_is_rejected_by_configured_backend_domain(): void
    {
        config(['mfa.student_email_domain' => 'students.example.edu']);

        $this->registerStudent('s12345678@student.usp.ac.fj')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
        $this->registerStudent('s12345678@students.example.edu')->assertCreated();
    }

    public function test_correct_registration_otp_verifies_email_activates_account_and_is_single_use(): void
    {
        $registration = $this->registerStudent()->assertCreated();
        $student = User::query()->where('email', 's12345678@student.usp.ac.fj')->sole();
        $payload = [
            'challenge_id' => $registration->json('mfa.challenge_id'),
            'code' => $this->latestCodeFor($student),
        ];

        $verified = $this->postJson('/api/v1/auth/verify-otp', $payload)
            ->assertOk()
            ->assertJsonMissingPath('user.email')
            ->assertJsonMissingPath('user.pseudonymous_uuid');

        $student->refresh();
        $this->assertSame('active', $student->account_status);
        $this->assertNotNull($student->email_verified_at);
        $this->assertNotEmpty($verified->json('token'));
        $this->assertDatabaseHas('user_mfa_methods', [
            'user_id' => $student->id,
            'method' => 'email',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/verify-otp', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_incorrect_and_expired_codes_are_rejected(): void
    {
        $registration = $this->registerStudent()->assertCreated();
        $challenge = EmailOtpChallenge::query()->findOrFail($registration->json('mfa.challenge_id'));
        $wrongCode = $this->differentCode($this->latestCodeFor($challenge->user));

        $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $challenge->id,
            'code' => $wrongCode,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(1, $challenge->fresh()->attempts);

        $challenge->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $challenge->id,
            'code' => $this->latestCodeFor($challenge->user),
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_maximum_attempts_prevents_otp_brute_force(): void
    {
        config(['mfa.otp_max_attempts' => 3]);
        $registration = $this->registerStudent()->assertCreated();
        $student = User::query()->where('email', 's12345678@student.usp.ac.fj')->sole();
        $wrongCode = $this->differentCode($this->latestCodeFor($student));
        $payload = ['challenge_id' => $registration->json('mfa.challenge_id'), 'code' => $wrongCode];

        foreach (range(1, 3) as $_) {
            $this->postJson('/api/v1/auth/verify-otp', $payload)->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/verify-otp', $payload)->assertTooManyRequests();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_resend_cooldown_and_rotation_invalidate_previous_code(): void
    {
        config(['mfa.resend_cooldown_seconds' => 60]);
        $registration = $this->registerStudent()->assertCreated();
        $student = User::query()->where('email', 's12345678@student.usp.ac.fj')->sole();
        $firstId = $registration->json('mfa.challenge_id');
        $firstCode = $this->latestCodeFor($student);

        $this->postJson('/api/v1/auth/resend-otp', ['challenge_id' => $firstId])
            ->assertTooManyRequests();

        $this->travel(61)->seconds();
        $resent = $this->postJson('/api/v1/auth/resend-otp', ['challenge_id' => $firstId])
            ->assertOk();
        $secondId = $resent->json('mfa.challenge_id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertNotNull(EmailOtpChallenge::query()->findOrFail($firstId)->invalidated_at);
        $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $firstId,
            'code' => $firstCode,
        ])->assertUnprocessable();
        $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $secondId,
            'code' => $this->latestCodeFor($student),
        ])->assertOk();
    }

    public function test_password_login_alone_cannot_access_protected_routes_but_mfa_can(): void
    {
        $student = User::factory()->create([
            'email' => 's76543210@student.usp.ac.fj',
            'password' => 'student-password',
            'role' => User::ROLE_STUDENT,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'test device',
        ])->assertOk()->assertJsonMissingPath('token');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/assessments', [])->assertUnauthorized();

        $verified = $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $login->json('mfa.challenge_id'),
            'code' => $this->latestCodeFor($student),
        ])->assertOk();
        $this->withToken($verified->json('token'))->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_challenge_is_bound_to_its_student_and_anonymous_identity_is_preserved(): void
    {
        $first = User::factory()->create([
            'email' => 's11111111@student.usp.ac.fj',
            'password' => 'student-password',
        ]);
        $second = User::factory()->create([
            'email' => 's22222222@student.usp.ac.fj',
            'password' => 'student-password',
        ]);
        $identityId = $first->studentIdentity->id;
        $uuid = $first->studentIdentity->pseudonymous_uuid;

        $firstLogin = $this->login($first);
        $secondLogin = $this->login($second);
        $firstCode = $this->latestCodeFor($first);

        $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $secondLogin->json('mfa.challenge_id'),
            'code' => $this->differentCode($this->latestCodeFor($second), $firstCode),
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $firstLogin->json('mfa.challenge_id'),
            'code' => $firstCode,
        ])->assertOk()->assertJsonMissingPath('user.email');

        $first->refresh();
        $this->assertSame($identityId, $first->studentIdentity->id);
        $this->assertSame($uuid, $first->studentIdentity->pseudonymous_uuid);
    }

    private function registerStudent(string $email = 's12345678@student.usp.ac.fj')
    {
        return $this->postJson('/api/v1/auth/register', [
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
            ],
        ]);
    }

    private function login(User $student)
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $student->email,
            'password' => 'student-password',
            'device_name' => 'test device',
        ])->assertOk();
    }

    private function latestCodeFor(User $student): string
    {
        $notification = Notification::sent($student, EmailOtpNotification::class)->last();
        $this->assertNotNull($notification);

        return $notification->code;
    }

    private function differentCode(string $code, ?string $avoid = null): string
    {
        $candidate = str_pad((string) (((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        return $candidate === $avoid
            ? str_pad((string) (((int) $candidate + 1) % 1000000), 6, '0', STR_PAD_LEFT)
            : $candidate;
    }
}
