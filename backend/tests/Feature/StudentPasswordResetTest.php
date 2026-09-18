<?php

namespace Tests\Feature;

use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_request_response_does_not_reveal_whether_a_student_exists(): void
    {
        $student = $this->student();
        $known = $this->requestCode($student->email)->assertOk();
        $unknown = $this->requestCode('missing@student.usp.ac.fj')->assertOk();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'admin@student.usp.ac.fj']);
        $administrator = $this->requestCode($admin->email)->assertOk();

        $this->assertSame($known->json(), $unknown->json());
        $this->assertSame($known->json(), $administrator->json());
        $this->assertArrayNotHasKey('challenge_id', $known->json());
        Notification::assertSentTo($student, EmailOtpNotification::class);
        Notification::assertNotSentTo($admin, EmailOtpNotification::class);
        $this->assertSame(1, EmailOtpChallenge::query()->where('purpose', EmailOtpChallenge::PURPOSE_PASSWORD_RESET)->count());
    }

    public function test_valid_code_must_be_verified_before_password_changes_and_is_single_use(): void
    {
        $student = $this->student();
        $this->requestCode($student->email)->assertOk();
        $code = $this->codeFor($student);
        $challenge = EmailOtpChallenge::query()->where('purpose', EmailOtpChallenge::PURPOSE_PASSWORD_RESET)->sole();

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $code))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $code])
            ->assertOk()->assertJsonMissingPath('token');
        $wrong = $code === '000000' ? '000001' : '000000';
        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $wrong))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $code))->assertOk();

        $this->assertTrue(Hash::check('new-password', $student->fresh()->password));
        $this->assertNotNull($challenge->fresh()->invalidated_at);
        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $code))
            ->assertUnprocessable();
    }

    public function test_invalid_expired_and_exhausted_codes_cannot_reset_password(): void
    {
        config(['mfa.otp_max_attempts' => 2]);
        $student = $this->student();
        $this->requestCode($student->email)->assertOk();
        $code = $this->codeFor($student);
        $wrong = $code === '000000' ? '000001' : '000000';
        $challenge = EmailOtpChallenge::query()->where('purpose', EmailOtpChallenge::PURPOSE_PASSWORD_RESET)->sole();

        foreach (range(1, 2) as $_) {
            $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $wrong])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
        }
        $this->assertSame(2, $challenge->fresh()->attempts);
        $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $code])
            ->assertTooManyRequests();

        $this->travel(61)->seconds();
        $this->requestCode($student->email)->assertOk();
        $freshCode = $this->codeFor($student);
        $fresh = EmailOtpChallenge::query()->where('purpose', EmailOtpChallenge::PURPOSE_PASSWORD_RESET)->latest('created_at')->firstOrFail();
        $fresh->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $freshCode])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertFalse(Hash::check('new-password', $student->fresh()->password));
    }

    public function test_password_rules_and_confirmation_match_registration(): void
    {
        $student = $this->student();
        $this->requestCode($student->email)->assertOk();
        $code = $this->codeFor($student);
        $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $code])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $code, 'short', 'short'))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $code, 'new-password', 'different'))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertFalse(Hash::check('new-password', $student->fresh()->password));
    }

    public function test_verified_code_still_expires_before_password_submission(): void
    {
        $student = $this->student();
        $this->requestCode($student->email)->assertOk();
        $code = $this->codeFor($student);
        $challenge = EmailOtpChallenge::query()->where('purpose', EmailOtpChallenge::PURPOSE_PASSWORD_RESET)->sole();
        $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $code])->assertOk();

        $challenge->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $code))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertTrue(Hash::check('old-password', $student->fresh()->password));
    }

    public function test_requesting_another_code_invalidates_a_previously_verified_reset_code(): void
    {
        $student = $this->student();
        $this->requestCode($student->email)->assertOk();
        $code = $this->codeFor($student);
        $challenge = EmailOtpChallenge::query()->where('purpose', EmailOtpChallenge::PURPOSE_PASSWORD_RESET)->sole();
        $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $code])->assertOk();

        $this->requestCode($student->email)->assertOk();
        $this->assertNotNull($challenge->fresh()->invalidated_at);
        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $code))
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertTrue(Hash::check('old-password', $student->fresh()->password));
    }

    public function test_password_reset_does_not_accept_login_code_or_leave_existing_sessions_active(): void
    {
        $student = $this->student();
        $token = $student->createToken('old device', ['student'])->plainTextToken;
        $this->requestCode($student->email)->assertOk();
        $resetCode = $this->codeFor($student);
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $student->email, 'password' => 'old-password', 'device_name' => 'test device',
        ])->assertOk();
        $loginCode = $resetCode === '000000' ? '000001' : '000000';
        EmailOtpChallenge::query()->findOrFail($login->json('mfa.challenge_id'))
            ->update(['otp_hash' => Hash::make($loginCode)]);

        $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $loginCode])
            ->assertUnprocessable();
        $this->postJson('/api/v1/auth/verify-reset-code', ['email' => $student->email, 'code' => $resetCode])
            ->assertOk();
        $this->postJson('/api/v1/auth/reset-password', $this->resetPayload($student->email, $resetCode))->assertOk();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', [
            'email' => $student->email, 'password' => 'old-password', 'device_name' => 'test device',
        ])->assertUnprocessable();
    }

    public function test_reset_code_cannot_be_used_to_sign_in(): void
    {
        $student = $this->student();
        $this->requestCode($student->email)->assertOk();
        $challenge = EmailOtpChallenge::query()->where('purpose', EmailOtpChallenge::PURPOSE_PASSWORD_RESET)->sole();

        $this->postJson('/api/v1/auth/verify-otp', [
            'challenge_id' => $challenge->id,
            'code' => $this->codeFor($student),
        ])->assertUnprocessable()->assertJsonMissingPath('token');
        $this->postJson('/api/v1/auth/resend-otp', ['challenge_id' => $challenge->id])
            ->assertUnprocessable()->assertJsonMissingPath('mfa.challenge_id');
        $this->assertNull($challenge->fresh()->verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    private function student(): User
    {
        return User::factory()->create([
            'email' => 'student@student.usp.ac.fj',
            'password' => Hash::make('old-password'),
        ]);
    }

    private function requestCode(string $email)
    {
        return $this->postJson('/api/v1/auth/forgot-password', ['email' => $email]);
    }

    private function codeFor(User $student): string
    {
        return Notification::sent($student, EmailOtpNotification::class)->last()->code;
    }

    private function resetPayload(string $email, string $code, string $password = 'new-password', string $confirmation = 'new-password'): array
    {
        return [
            'email' => $email,
            'code' => $code,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ];
    }
}
