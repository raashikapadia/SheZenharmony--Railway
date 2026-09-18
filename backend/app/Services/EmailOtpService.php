<?php

namespace App\Services;

use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Notifications\EmailOtpNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class EmailOtpService
{
    public function issue(User $user, string $purpose, string $deviceName): EmailOtpChallenge
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresMinutes = max(1, (int) config('mfa.otp_expires_minutes'));

        $challenge = DB::transaction(function () use ($user, $purpose, $deviceName, $code, $expiresMinutes): EmailOtpChallenge {
            $previous = EmailOtpChallenge::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('invalidated_at');
            if ($purpose !== EmailOtpChallenge::PURPOSE_PASSWORD_RESET) {
                $previous->whereNull('verified_at');
            }
            $previous->update(['invalidated_at' => now()]);

            return EmailOtpChallenge::query()->create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'otp_hash' => Hash::make($code),
                'device_name' => $deviceName,
                'expires_at' => now()->addMinutes($expiresMinutes),
            ]);
        });

        try {
            $user->notify(new EmailOtpNotification($code, $expiresMinutes, $purpose));
        } catch (TransportExceptionInterface $exception) {
            $challenge->update(['invalidated_at' => now()]);
            report($exception);

            throw new ServiceUnavailableHttpException(
                null,
                'We could not send the verification email. Please try again shortly.'
            );
        } catch (Throwable $exception) {
            $challenge->update(['invalidated_at' => now()]);
            throw $exception;
        }

        return $challenge;
    }

    public function verify(string $challengeId, string $code): array
    {
        $result = DB::transaction(function () use ($challengeId, $code): array {
            $challenge = EmailOtpChallenge::query()
                ->with('user')
                ->lockForUpdate()
                ->find($challengeId);

            if (! $challenge || $challenge->invalidated_at !== null || ! in_array($challenge->purpose, [
                EmailOtpChallenge::PURPOSE_LOGIN,
                EmailOtpChallenge::PURPOSE_REGISTRATION,
            ], true)) {
                $this->invalidCode();
            }

            if ($challenge->verified_at !== null) {
                throw ValidationException::withMessages([
                    'code' => ['This verification code has already been used.'],
                ]);
            }

            if ($challenge->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'code' => ['This verification code has expired. Request a new code.'],
                ]);
            }

            $maxAttempts = max(1, (int) config('mfa.otp_max_attempts'));
            if ($challenge->attempts >= $maxAttempts) {
                throw new TooManyRequestsHttpException(null, 'Too many verification attempts. Request a new code.');
            }

            if (! Hash::check($code, $challenge->otp_hash)) {
                $challenge->increment('attempts');

                return ['invalid' => true];
            }

            $challenge->forceFill(['verified_at' => now()])->save();
            $user = $challenge->user;

            if ($challenge->purpose === EmailOtpChallenge::PURPOSE_REGISTRATION) {
                if ($user->account_status !== 'pending_verification') {
                    $this->invalidCode();
                }

                $user->forceFill([
                    'account_status' => 'active',
                ])->save();
            } elseif (! $user->isStudent() || $user->account_status !== 'active') {
                $this->invalidCode();
            }

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->mfaMethods()->updateOrCreate(
                ['method' => 'email'],
                ['is_active' => true, 'confirmed_at' => now(), 'last_used_at' => now()]
            );

            return [$user, $challenge->device_name];
        });

        if (isset($result['invalid'])) {
            $this->invalidCode();
        }

        return $result;
    }

    public function resend(string $challengeId): EmailOtpChallenge
    {
        $challenge = EmailOtpChallenge::query()->with('user')->find($challengeId);
        if (! $challenge || ! in_array($challenge->purpose, [
            EmailOtpChallenge::PURPOSE_LOGIN,
            EmailOtpChallenge::PURPOSE_REGISTRATION,
        ], true) || $challenge->verified_at !== null || $challenge->invalidated_at !== null) {
            throw ValidationException::withMessages([
                'challenge_id' => ['This verification request is no longer available.'],
            ]);
        }

        $cooldown = max(0, (int) config('mfa.resend_cooldown_seconds'));
        $availableAt = $challenge->created_at->copy()->addSeconds($cooldown);
        if (now()->lt($availableAt)) {
            throw new TooManyRequestsHttpException(
                now()->diffInSeconds($availableAt),
                'Please wait before requesting another verification code.'
            );
        }

        return $this->issue($challenge->user, $challenge->purpose, $challenge->device_name);
    }

    public function response(EmailOtpChallenge $challenge): array
    {
        return [
            'challenge_id' => $challenge->id,
            'purpose' => $challenge->purpose,
            'masked_email' => $this->maskEmail($challenge->user->email),
            'expires_in_seconds' => (int) max(0, now()->diffInSeconds($challenge->expires_at)),
            'resend_after_seconds' => max(0, (int) config('mfa.resend_cooldown_seconds')),
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, 1);

        return $visible.'***@'.$domain;
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages([
            'code' => ['The verification code is invalid.'],
        ]);
    }
}
