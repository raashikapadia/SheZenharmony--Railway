<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->payload($user)]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'email' => ['sometimes', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'country' => ['sometimes', 'string', 'max:100'],
            'year_of_study' => ['sometimes', 'string', 'max:30'],
            'employment_status' => ['sometimes', 'string', 'max:100'],
            'relationship_status' => ['sometimes', 'string', 'max:100'],
            'has_children' => ['sometimes', 'boolean'],
            'living_situation' => ['sometimes', 'string', 'max:150'],
        ], [
            'date_of_birth.before' => 'Date of birth cannot be in the future.',
            'email.unique' => 'An account with this email already exists.',
        ]);

        if (array_key_exists('email', $data)) {
            $user->update(['email' => $data['email']]);
            unset($data['email']);
        }

        if ($data !== []) {
            $identity = $user->studentIdentity()->firstOrFail();
            $identity->profile()->updateOrCreate([], $data);
        }

        return response()->json([
            'message' => 'Your profile has been updated successfully.',
            'data' => $this->payload($user->fresh()),
        ]);
    }

    private function payload(User $user): array
    {
        $identity = $user->studentIdentity()->firstOrFail();
        $profile = $identity->profile;

        return [
            'email' => $user->email,
            'date_of_birth' => $profile?->date_of_birth?->toDateString(),
            'age' => $profile?->age,
            'country' => $profile?->country,
            'year_of_study' => $profile?->year_of_study,
            'employment_status' => $profile?->employment_status,
            'relationship_status' => $profile?->relationship_status,
            'has_children' => $profile?->has_children,
            'living_situation' => $profile?->living_situation,
        ];
    }
}
