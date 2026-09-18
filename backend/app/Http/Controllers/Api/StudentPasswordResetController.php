<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudentPasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class StudentPasswordResetController extends Controller
{
    public function requestCode(Request $request, StudentPasswordResetService $reset): JsonResponse
    {
        $email = $this->email($request);

        try {
            $reset->requestCode($email);
        } catch (ServiceUnavailableHttpException) {
            // Use the same response for missing accounts and mail delivery failures.
        }

        return response()->json([
            'message' => 'If this student email is registered, a password reset code has been sent.',
        ]);
    }

    public function verifyCode(Request $request, StudentPasswordResetService $reset): JsonResponse
    {
        $email = $this->email($request);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $reset->verifyCode($email, $data['code']);

        return response()->json(['message' => 'Verification code confirmed.']);
    }

    public function reset(Request $request, StudentPasswordResetService $reset): JsonResponse
    {
        $email = $this->email($request);
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $reset->reset($email, $data['code'], $data['password']);

        return response()->json(['message' => 'Password reset successfully. Please sign in.']);
    }

    private function email(Request $request): string
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        return $request->validate(['email' => ['required', 'email:rfc', 'max:255']])['email'];
    }
}
