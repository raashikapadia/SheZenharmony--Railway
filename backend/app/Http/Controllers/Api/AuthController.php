<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => User::ROLE_STUDENT,
        ]);
        $user->assignRole(User::ROLE_STUDENT);

        return response()->json([
            'token' => $user->createToken($data['device_name'], ['student'])->plainTextToken,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
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

        $ability = match (true) {
            $user->isAdmin() => 'admin',
            $user->isStudent() => 'student',
            default => null,
        };

        if ($ability === null) {
            throw ValidationException::withMessages([
                'email' => ['This account type cannot sign in here.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken($credentials['device_name'], [$ability])->plainTextToken,
            'user' => $this->userPayload($user),
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

    /**
     * A user's required onboarding questionnaire is satisfied by having
     * completed ANY stress assessment — it is a one-time gate, not tied to
     * a specific questionnaire version. Derived from real assessment rows
     * rather than a separate flag, so it can never drift from the truth.
     */
    private function userPayload(User $user): array
    {
        return $user->toArray() + [
            'has_completed_required_assessment' => $user->stressAssessments()
                ->where('assessment_status', 'completed')
                ->exists(),
        ];
    }
}
