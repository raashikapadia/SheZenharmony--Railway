<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /*
         * Admin login rate limiting:
         * 3 failed attempts are allowed.
         * The next attempt is blocked for 5 minutes.
         */
        $loginKey = 'admin-login:' . strtolower($credentials['email']) . '|' . $request->ip();

        Log::info('ADMIN LOGIN DEBUG - request received', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
            'too_many_attempts' => RateLimiter::tooManyAttempts($loginKey, 3),
        ]);

        if (RateLimiter::tooManyAttempts($loginKey, 3)) {
            $seconds = RateLimiter::availableIn($loginKey);

            Log::warning('ADMIN LOGIN DEBUG - rate limited', [
                'email' => $credentials['email'],
                'seconds_remaining' => $seconds,
            ]);

            return back()
                ->withErrors([
                    'email' => 'Too many failed login attempts. Please try again in 5 minutes.',
                ])
                ->onlyInput('email');
        }

        /*
         * Attempt authentication.
         */
        $authenticated = Auth::attempt(
            $credentials,
            $request->boolean('remember')
        );

        Log::info('ADMIN LOGIN DEBUG - Auth::attempt', [
            'authenticated' => $authenticated,
        ]);

        if (! $authenticated) {
            RateLimiter::hit($loginKey, 300);

            Log::warning('ADMIN LOGIN DEBUG - Auth::attempt FAILED', [
                'email' => $credentials['email'],
            ]);

            return back()
                ->withErrors([
                    'email' => 'The supplied administrator credentials are incorrect.',
                ])
                ->onlyInput('email');
        }

        /*
         * Authentication succeeded.
         * Check that the authenticated user is an active administrator.
         */
        $user = Auth::user();

        Log::info('ADMIN LOGIN DEBUG - user check', [
            'id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'account_status' => $user?->account_status,
            'is_admin' => $user?->isAdmin(),
        ]);

        if (! $user?->isAdmin() || $user?->account_status !== 'active') {
            Auth::logout();

            RateLimiter::hit($loginKey, 300);

            Log::warning('ADMIN LOGIN DEBUG - ADMIN CHECK FAILED', [
                'id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'account_status' => $user?->account_status,
                'is_admin' => $user?->isAdmin(),
            ]);

            return back()
                ->withErrors([
                    'email' => 'The supplied administrator credentials are incorrect.',
                ])
                ->onlyInput('email');
        }

        /*
         * Successful admin login.
         */
        RateLimiter::clear($loginKey);

        $request->session()->regenerate();

        Log::info('ADMIN LOGIN DEBUG - LOGIN SUCCESSFUL', [
            'id' => $user->id,
            'email' => $user->email,
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}