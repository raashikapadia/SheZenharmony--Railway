<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const STUDENT_PRIVACY_POLICY_VERSION = 'shezen-privacy-notice-v1-draft';

    public function register(Request $request, EmailOtpService $otpService): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(
            [
                'email' => [
                    'required',
                    'email:rfc',
                    'max:255',
                    function (string $attribute, mixed $value, \Closure $fail): void {
                        $domain = (string) config('mfa.student_email_domain');
                        if ($domain === '' || ! Str::endsWith(strtolower((string) $value), '@'.$domain)) {
                            $fail("Use your USP student email ending in @{$domain}.");
                        }
                    },
                    'unique:users,email',
                ],
                'password' => ['required', 'confirmed', Password::min(8)],
                'device_name' => ['required', 'string', 'max:100'],
                'privacy_consent' => ['required', 'accepted'],
                'demographics' => ['required', 'array'],
                'demographics.date_of_birth' => ['required', 'date', 'before:today'],
                'demographics.year_of_study' => ['required', 'string', 'max:30'],
                'demographics.gender' => ['required', 'string', 'max:50'],
                'demographics.country' => ['required', 'string', 'max:100'],
                'demographics.employment_status' => ['required', 'string', 'max:100'],
                'demographics.relationship_status' => ['required', 'string', 'max:100'],
                'demographics.has_children' => ['required', 'boolean'],
                'demographics.living_situation' => ['required', 'string', 'max:150'],
                'demographics.preferred_language' => ['sometimes', 'nullable', 'string', 'max:50'],
            ],
            [
                'email.email' => 'Please enter a valid email address.',
                'email.unique' => 'An account with this email already exists.',
                'demographics.date_of_birth.before' => 'Date of birth cannot be in the future.',
                'privacy_consent.accepted' => 'Please acknowledge the Privacy & Data Use information.',
            ]
        );

        $challenge = DB::transaction(function () use ($data, $otpService): EmailOtpChallenge {
            $user = User::query()->create([
                'name' => null,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_STUDENT,
                'account_status' => 'pending_verification',
            ]);
            $user->assignRole(User::ROLE_STUDENT);

            $identity = $user->studentIdentity()->firstOrFail();
            $identity->profile()->create($data['demographics']);
            $identity->consents()->create([
                'policy_version' => self::STUDENT_PRIVACY_POLICY_VERSION,
                'accepted_at' => now(),
            ]);

            return $otpService->issue(
                $user,
                EmailOtpChallenge::PURPOSE_REGISTRATION,
                $data['device_name']
            );
        });

        return response()->json([
            'message' => 'A verification code was sent to your USP student email.',
            'mfa' => $otpService->response($challenge),
        ], 201);
    }

    public function login(Request $request, EmailOtpService $otpService): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The supplied credentials are incorrect.'],
            ]);
        }

        if (! $user->isStudent() || ! in_array($user->account_status, ['active', 'pending_verification'], true)) {
            throw ValidationException::withMessages([
                'email' => ['This account cannot sign in to the student application.'],
            ]);
        }

        $user->studentIdentity()->firstOrCreate([], [
            'pseudonymous_uuid' => $user->pseudonymous_uuid,
        ]);

        $challenge = $otpService->issue(
            $user,
            $user->account_status === 'pending_verification'
                ? EmailOtpChallenge::PURPOSE_REGISTRATION
                : EmailOtpChallenge::PURPOSE_LOGIN,
            $credentials['device_name']
        );

        return response()->json([
            'message' => 'A verification code was sent to your USP student email.',
            'mfa' => $otpService->response($challenge),
        ]);
    }

    public function verifyOtp(Request $request, EmailOtpService $otpService): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ]);

        [$user, $deviceName] = $otpService->verify($data['challenge_id'], $data['code']);

        return response()->json([
            'token' => $user->createToken($deviceName, ['student'])->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function resendOtp(Request $request, EmailOtpService $otpService): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'uuid'],
        ]);
        $challenge = $otpService->resend($data['challenge_id']);

        return response()->json([
            'message' => 'A new verification code was sent to your USP student email.',
            'mfa' => $otpService->response($challenge),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out successfully.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isStudent(), 403);

        DB::transaction(function () use ($user): void {
            $identity = $user->studentIdentity()->first();
            if ($identity !== null) {
                $chatSessionIds = $identity->chatSessions()->pluck('id');
                DB::table('crisis_reports')->whereIn('chat_session_id', $chatSessionIds)->delete();
                $identity->delete();
            }

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->forceDelete();
        });

        return response()->json(['message' => 'Account deleted successfully.']);
    }

    /**
     * A user's required onboarding questionnaire is satisfied by having
     * completed ANY stress assessment — it is a one-time gate, not tied to
     * a specific questionnaire version. Derived from real assessment rows
     * rather than a separate flag, so it can never drift from the truth.
     */
    private function userPayload(User $user): array
    {
        $identity = $user->studentIdentity()->firstOrFail();

        return [
            'role' => User::ROLE_STUDENT,
            'shezen_id' => $identity->displayId(),
            'has_completed_required_assessment' => $identity->assessments()
                ->where('assessment_status', 'completed')
                ->exists(),
        ];
    }
}
