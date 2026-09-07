<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isStudent() && $user->account_status === 'active') {
            return $next($request);
        }

        if ($user?->isStudent() && $user->account_status === 'suspended') {
            return new JsonResponse([
                'code' => 'account_on_hold',
                'message' => $this->holdMessage($user),
            ], 423);
        }

        abort(403);
    }

    private function holdMessage(User $user): string
    {
        $message = 'Your SheZen Harmony account is currently on hold.';

        if ($user->account_hold_reason) {
            $message .= ' Reason: '.$user->account_hold_reason;
        }

        return $message.' Please contact SheZen Harmony support if you believe this is a mistake.';
    }
}
