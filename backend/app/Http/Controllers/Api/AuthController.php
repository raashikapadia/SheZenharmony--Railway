<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailOtpChallenge;
use App\Models\StudentConsent;
use App\Models\User;
use App\Models\UserProfile;
use App\Rules\MinimumAge;
use App\Services\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(
        Request $request,
        EmailOtpService $otpService
    ): JsonResponse {
        $request->merge([
            'email' => strtolower(
                trim((string) $request->input('email'))
            ),
        ]);

        $data = $request->validate(
            [
                'email' => [
                    'required',
                    'email:rfc',
                    'max:255',
                    function (
                        string $attribute,
                        mixed $value,
                        \Closure $fail
                    ): void {
                        $domain = (string) config(
                            'mfa.student_email_domain'
                        );

                        if (
                            $domain === '' ||
                            !Str::endsWith(
                                strtolower((string) $value),
                                '@' . $domain
                            )
                        ) {
                            $fail(
                                "Use your USP student email ending in @{$domain}."
                            );
                        }
                    },
                    'unique:users,email',
                ],

                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8),
                ],

                'device_name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'privacy_consent' => [
                    'required',
                    'accepted',
                ],

                'demographics' => [
                    'required',
                    'array',
                ],

                'demographics.date_of_birth' => [
                    'required',
                    'date',
                    'before:today',
                    new MinimumAge(18),
                ],

                'demographics.year_of_study' => [
                    'required',
                    'string',
                    Rule::in(
                        UserProfile::YEAR_OF_STUDY_OPTIONS
                    ),
                ],

                'demographics.year_of_study_detail' => [
                    'required_if:demographics.year_of_study,' .
                        UserProfile::YEAR_OF_STUDY_OTHER,
                    'nullable',
                    'string',
                    'max:100',
                ],

                'demographics.gender' => [
                    'required',
                    'string',
                    'max:50',
                ],

                'demographics.country' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'demographics.employment_status' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'demographics.relationship_status' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'demographics.has_children' => [
                    'required',
                    'boolean',
                ],

                'demographics.living_situation' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'demographics.preferred_language' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:50',
                ],
            ],
            [
                'email.email' =>
                    'Please enter a valid email address.',

                'email.unique' =>
                    'An account with this email already exists.',

                'demographics.date_of_birth.before' =>
                    'Date of birth cannot be in the future.',

                'demographics.year_of_study.in' =>
                    'Select your year of study.',

                'demographics.year_of_study_detail.required_if' =>
                    'Please specify your year of study.',

                'privacy_consent.accepted' =>
                    'You must agree to participate in the survey to create an account.',
            ]
        );

        $challenge = DB::transaction(
            function () use (
                $data,
                $otpService
            ): EmailOtpChallenge {
                $user = User::query()->create([
                    'name' => null,
                    'email' => $data['email'],
                    'password' => Hash::make(
                        $data['password']
                    ),
                    'role' => User::ROLE_STUDENT,
                    'account_status' => 'pending_verification',
                ]);

                $user->assignRole(
                    User::ROLE_STUDENT
                );

                $identity = $user
                    ->studentIdentity()
                    ->firstOrFail();

                $identity
                    ->profile()
                    ->create(
                        $data['demographics']
                    );

                $identity->recordCurrentConsent();

                return $otpService->issue(
                    $user,
                    EmailOtpChallenge::PURPOSE_REGISTRATION,
                    $data['device_name']
                );
            }
        );

        return response()->json([
            'message' =>
                'A verification code was sent to your USP student email.',

            'mfa' =>
                $otpService->response($challenge),
        ], 201);
    }

    /**
     * Login.
     *
     * Maximum 3 failed password attempts.
     * After 3 failures, login is locked for 5 minutes.
     *
     * Successful password authentication continues
     * through the existing OTP/MFA process.
     */
    public function login(
        Request $request,
        EmailOtpService $otpService
    ): JsonResponse {
        $request->merge([
            'email' => strtolower(
                trim((string) $request->input('email'))
            ),
        ]);

        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],

            'device_name' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $email = $credentials['email'];

        /*
         * Cache keys for failed attempts and lockout.
         */
        $attemptsKey = 'login_attempts:' . $email;
        $lockKey = 'login_locked:' . $email;

        /*
         * Check whether this email is currently locked.
         */
        if (Cache::has($lockKey)) {
            return response()->json([
                'code' => 'login_locked',
                'message' =>
                    'Too many failed login attempts. Try again later.',
                'retry_after' => 300,
            ], 429);
        }

        /*
         * Find the user.
         */
        $user = User::query()
            ->where('email', $email)
            ->first();

        /*
         * Wrong email OR wrong password counts
         * as one failed attempt.
         */
        if (
            !$user ||
            !Hash::check(
                $credentials['password'],
                $user->password
            )
        ) {
            /*
             * Get current failed attempts.
             */
            $attempts = (int) Cache::get(
                $attemptsKey,
                0
            );

            /*
             * Increase failed attempts.
             */
            $attempts++;

            /*
             * Store the number of attempts for 5 minutes.
             */
            Cache::put(
                $attemptsKey,
                $attempts,
                now()->addMinutes(5)
            );

            /*
             * Third failed attempt.
             *
             * Lock the email for 5 minutes.
             */
            if ($attempts >= 3) {
                Cache::put(
                    $lockKey,
                    true,
                    now()->addMinutes(5)
                );

                Cache::forget($attemptsKey);

                return response()->json([
                    'code' => 'login_locked',
                    'message' =>
                        'Too many failed login attempts. Try again later.',
                    'retry_after' => 300,
                ], 429);
            }

            /*
             * Tell the user how many attempts remain.
             */
            $remainingAttempts = 3 - $attempts;

            $attemptMessage = $remainingAttempts === 1
                ? '1 attempt remaining.'
                : "{$remainingAttempts} attempts remaining.";

            throw ValidationException::withMessages([
                'email' => [
                    'The supplied credentials are incorrect.',
                ],

                'attempts_remaining' => [
                    $attemptMessage,
                ],
            ]);
        }

        /*
         * Correct password.
         *
         * Reset failed login attempts.
         */
        Cache::forget($attemptsKey);
        Cache::forget($lockKey);

        /*
         * Existing suspended-account check.
         */
        if (
            $user->isStudent() &&
            $user->account_status === 'suspended'
        ) {
            return response()->json([
                'code' => 'account_on_hold',

                'message' =>
                    $this->accountHoldMessage($user),
            ], 423);
        }

        /*
         * Existing account status check.
         */
        if (
            !$user->isStudent() ||
            !in_array(
                $user->account_status,
                [
                    'active',
                    'pending_verification',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'This account cannot sign in to the student application.',
                ],
            ]);
        }

        /*
         * Make sure the student identity exists.
         */
        $user->studentIdentity()->firstOrCreate(
            [],
            [
                'pseudonymous_uuid' =>
                    $user->pseudonymous_uuid,
            ]
        );

        /*
         * Existing OTP process.
         */
        $challenge = $otpService->issue(
            $user,

            $user->account_status === 'pending_verification'
                ? EmailOtpChallenge::PURPOSE_REGISTRATION
                : EmailOtpChallenge::PURPOSE_LOGIN,

            $credentials['device_name']
        );

        return response()->json([
            'message' =>
                'A verification code was sent to your USP student email.',

            'mfa' =>
                $otpService->response($challenge),
        ]);
    }

    public function verifyOtp(
        Request $request,
        EmailOtpService $otpService
    ): JsonResponse {
        $data = $request->validate([
            'challenge_id' => [
                'required',
                'uuid',
            ],

            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        [$user, $deviceName] = $otpService->verify(
            $data['challenge_id'],
            $data['code']
        );

        return response()->json([
            'token' => $user
                ->createToken(
                    $deviceName,
                    ['student']
                )
                ->plainTextToken,

            'user' =>
                $this->userPayload($user),
        ]);
    }

    public function resendOtp(
        Request $request,
        EmailOtpService $otpService
    ): JsonResponse {
        $data = $request->validate([
            'challenge_id' => [
                'required',
                'uuid',
            ],
        ]);

        $challenge = $otpService->resend(
            $data['challenge_id']
        );

        return response()->json([
            'message' =>
                'A new verification code was sent to your USP student email.',

            'mfa' =>
                $otpService->response($challenge),
        ]);
    }

    public function me(
        Request $request
    ): JsonResponse {
        return response()->json([
            'user' =>
                $this->userPayload(
                    $request->user()
                ),
        ]);
    }

    /**
     * Records the student's agreement to the current consent wording.
     */
    public function consent(
        Request $request
    ): JsonResponse {
        $request->validate([
            'privacy_consent' => [
                'required',
                'accepted',
            ],
        ]);

        /** @var User $user */
        $user = $request->user();

        $user
            ->studentIdentity()
            ->firstOrFail()
            ->recordCurrentConsent();

        return response()->json([
            'user' =>
                $this->userPayload($user),
        ]);
    }

    public function logout(
        Request $request
    ): JsonResponse {
        $request
            ->user()
            ->currentAccessToken()
            ?->delete();

        return response()->json([
            'message' =>
                'Signed out successfully.',
        ]);
    }

    public function destroy(
        Request $request
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $user->isStudent(),
            403
        );

        DB::transaction(
            function () use ($user): void {
                $identity =
                    $user->studentIdentity()->first();

                if ($identity !== null) {
                    $chatSessionIds =
                        $identity
                            ->chatSessions()
                            ->pluck('id');

                    DB::table('crisis_reports')
                        ->whereIn(
                            'chat_session_id',
                            $chatSessionIds
                        )
                        ->delete();

                    $identity->delete();
                }

                /*
                 * Delete Sanctum tokens.
                 */
                $user->tokens()->delete();

                /*
                 * Delete sessions.
                 */
                DB::table('sessions')
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->delete();

                /*
                 * Permanently delete account.
                 */
                $user->forceDelete();
            }
        );

        return response()->json([
            'message' =>
                'Account deleted successfully.',
        ]);
    }

    /**
     * Build the user payload returned to the app.
     */
    private function userPayload(
        User $user
    ): array {
        $identity =
            $user
                ->studentIdentity()
                ->firstOrFail();

        return [
            'role' =>
                User::ROLE_STUDENT,

            'shezen_id' =>
                $identity->displayId(),

            'has_completed_required_assessment' =>
                $identity
                    ->assessments()
                    ->where(
                        'assessment_status',
                        'completed'
                    )
                    ->exists(),

            'has_current_consent' =>
                $identity->hasCurrentConsent(),

            'consent_version' =>
                StudentConsent::CURRENT_POLICY_VERSION,
        ];
    }

    private function accountHoldMessage(
        User $user
    ): string {
        $message =
            'Your SheZen Harmony account is currently on hold.';

        if ($user->account_hold_reason) {
            $message .=
                ' Reason: ' .
                $user->account_hold_reason;
        }

        return $message .
            ' Please contact SheZen Harmony support if you believe this is a mistake.';
    }
}