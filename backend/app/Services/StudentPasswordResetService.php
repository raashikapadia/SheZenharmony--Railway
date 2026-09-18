<?php

namespace App\Services;

use App\Models\EmailOtpChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class StudentPasswordResetService
{
    public function __construct(private readonly EmailOtpService $otpService) {}

    public function requestCode(string $email): void
    {
        $user = $this->student($email);
        if ($user === null) {
            return;
        }

        $this->otpService->issue($user, EmailOtpChallenge::PURPOSE_PASSWORD_RESET, 'password reset');
    }

    public function verifyCode(string $email, string $code): void
    {
        $valid = DB::transaction(function () use ($email, $code): bool {
            $challenge = $this->challenge($email);
            if ($challenge === null || $challenge->verified_at !== null) {
                $this->invalidCode();
            }

            if (! $this->matches($challenge, $code)) {
                return false;
            }

            $challenge->forceFill(['verified_at' => now()])->save();

            return true;
        });

        if (! $valid) {
            $this->invalidCode();
        }
    }

    public function reset(string $email, string $code, string $password): void
    {
        $valid = DB::transaction(function () use ($email, $code, $password): bool {
            $challenge = $this->challenge($email);
            if ($challenge === null || $challenge->verified_at === null) {
                $this->invalidCode();
            }

            if (! $this->matches($challenge, $code)) {
                return false;
            }

            $user = $challenge->user;
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            EmailOtpChallenge::query()->where('user_id', $user->id)
                ->whereNull('invalidated_at')->update(['invalidated_at' => now()]);

            return true;
        });

        if (! $valid) {
            $this->invalidCode();
        }
    }

    private function student(string $email): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->isStudent() || $user->account_status !== 'active') {
            return null;
        }

        return $user;
    }

    private function challenge(string $email): ?EmailOtpChallenge
    {
        $user = $this->student($email);
        if ($user === null) {
            return null;
        }

        return EmailOtpChallenge::query()->with('user')
            ->where('user_id', $user->id)
            ->where('purpose', EmailOtpChallenge::PURPOSE_PASSWORD_RESET)
            ->whereNull('invalidated_at')
            ->latest('created_at')
            ->lockForUpdate()
            ->first();
    }

    private function matches(EmailOtpChallenge $challenge, string $code): bool
    {
        if ($challenge->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => ['This verification code has expired. Request a new code.'],
            ]);
        }

        if ($challenge->attempts >= max(1, (int) config('mfa.otp_max_attempts'))) {
            throw new TooManyRequestsHttpException(null, 'Too many verification attempts. Request a new code.');
        }

        if (! Hash::check($code, $challenge->otp_hash)) {
            $challenge->increment('attempts');

            return false;
        }

        return true;
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages([
            'code' => ['The verification code is invalid.'],
        ]);
    }
}
