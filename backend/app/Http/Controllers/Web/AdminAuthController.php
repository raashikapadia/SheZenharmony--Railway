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
    /**
     * Display the admin login page.
     */
    public function create(): View|RedirectResponse
    {
        // If already logged in as an administrator,
        // send the user directly to the dashboard.
        if (Auth::check() && Auth::user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Process administrator login.
     */
    public function store(Request $request): RedirectResponse
    {
        /*
         * Validate login input.
         */
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /*
         * Normalize email.
         *
         * This prevents accidental spaces or uppercase
         * differences from causing login problems.
         */
        $email = strtolower(trim($credentials['email']));

        $credentials['email'] = $email;

        /*
         * Rate-limit key.
         *
         * The limiter is based on:
         * email + IP address
         */
        $loginKey = 'admin-login:' . $email . '|' . $request->ip();

        /*
         * Maximum failed attempts.
         */
        $maxAttempts = 3;

        /*
         * Lockout duration:
         * 300 seconds = 5 minutes.
         */
        $decaySeconds = 300;

        /*
         * ---------------------------------------------------------
         * DEBUG: LOGIN REQUEST
         * ---------------------------------------------------------
         */
        Log::info('ADMIN LOGIN DEBUG - request received', [
            'email' => $email,
            'ip' => $request->ip(),
            'rate_limit_key' => $loginKey,
            'too_many_attempts' => RateLimiter::tooManyAttempts(
                $loginKey,
                $maxAttempts
            ),
            'attempts' => RateLimiter::attempts($loginKey),
        ]);

        /*
         * ---------------------------------------------------------
         * CHECK RATE LIMIT
         * ---------------------------------------------------------
         */
        if (RateLimiter::tooManyAttempts($loginKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($loginKey);

            Log::warning('ADMIN LOGIN DEBUG - rate limited', [
                'email' => $email,
                'ip' => $request->ip(),
                'attempts' => RateLimiter::attempts($loginKey),
                'seconds_remaining' => $seconds,
            ]);

            $minutes = max(1, (int) ceil($seconds / 60));

            return back()
                ->withErrors([
                    'email' => "Too many failed login attempts. Please try again in {$minutes} minute(s).",
                ])
                ->onlyInput('email');
        }

        /*
         * ---------------------------------------------------------
         * AUTHENTICATE USER
         * ---------------------------------------------------------
         */
        Log::info('ADMIN LOGIN DEBUG - attempting authentication', [
            'email' => $email,
        ]);

        $authenticated = Auth::attempt(
            $credentials,
            $request->boolean('remember')
        );

        /*
         * ---------------------------------------------------------
         * AUTHENTICATION RESULT
         * ---------------------------------------------------------
         */
        Log::info('ADMIN LOGIN DEBUG - Auth::attempt result', [
            'email' => $email,
            'authenticated' => $authenticated,
        ]);

        /*
         * ---------------------------------------------------------
         * WRONG EMAIL / PASSWORD
         * ---------------------------------------------------------
         */
        if (! $authenticated) {
            RateLimiter::hit($loginKey, $decaySeconds);

            Log::warning('ADMIN LOGIN DEBUG - AUTHENTICATION FAILED', [
                'email' => $email,
                'ip' => $request->ip(),
                'attempts_after_failure' => RateLimiter::attempts($loginKey),
            ]);

            return back()
                ->withErrors([
                    'email' => 'The supplied administrator credentials are incorrect.',
                ])
                ->onlyInput('email');
        }

        /*
         * ---------------------------------------------------------
         * AUTHENTICATION SUCCESSFUL
         * ---------------------------------------------------------
         */
        $user = Auth::user();

        /*
         * Log the actual authenticated user information.
         *
         * IMPORTANT:
         * Never log the password.
         */
        Log::info('ADMIN LOGIN DEBUG - authenticated user', [
            'id' => $user?->id,
            'email' => $user?->email,
            'role' => $user?->role,
            'account_status' => $user?->account_status,
            'is_admin' => $user?->isAdmin(),
        ]);

        /*
         * ---------------------------------------------------------
         * CHECK ADMIN ROLE
         * ---------------------------------------------------------
         */
        $isAdmin = $user?->isAdmin();

        /*
         * ---------------------------------------------------------
         * CHECK ACCOUNT STATUS
         * ---------------------------------------------------------
         */
        $isActive = $user?->account_status === 'active';

        /*
         * ---------------------------------------------------------
         * DEBUG ADMIN / ACCOUNT CHECK
         * ---------------------------------------------------------
         */
        Log::info('ADMIN LOGIN DEBUG - authorization checks', [
            'id' => $user?->id,
            'email' => $user?->email,
            'is_admin' => $isAdmin,
            'is_active' => $isActive,
            'role' => $user?->role,
            'account_status' => $user?->account_status,
        ]);

        /*
         * ---------------------------------------------------------
         * USER IS NOT AN ADMIN
         * ---------------------------------------------------------
         */
        if (! $isAdmin) {
            Log::warning('ADMIN LOGIN DEBUG - ADMIN ROLE CHECK FAILED', [
                'id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'is_admin' => $isAdmin,
            ]);

            Auth::logout();

            RateLimiter::hit($loginKey, $decaySeconds);

            return back()
                ->withErrors([
                    'email' => 'This account does not have administrator access.',
                ])
                ->onlyInput('email');
        }

        /*
         * ---------------------------------------------------------
         * ACCOUNT IS NOT ACTIVE
         * ---------------------------------------------------------
         */
        if (! $isActive) {
            Log::warning('ADMIN LOGIN DEBUG - ACCOUNT STATUS CHECK FAILED', [
                'id' => $user?->id,
                'email' => $user?->email,
                'role' => $user?->role,
                'account_status' => $user?->account_status,
            ]);

            Auth::logout();

            RateLimiter::hit($loginKey, $decaySeconds);

            return back()
                ->withErrors([
                    'email' => 'This administrator account is not active.',
                ])
                ->onlyInput('email');
        }

        /*
         * ---------------------------------------------------------
         * SUCCESSFUL ADMIN LOGIN
         * ---------------------------------------------------------
         */

        /*
         * Clear failed login attempts.
         */
        RateLimiter::clear($loginKey);

        /*
         * Regenerate session ID after successful login.
         */
        $request->session()->regenerate();

        /*
         * Final success log.
         */
        Log::info('ADMIN LOGIN DEBUG - LOGIN SUCCESSFUL', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'account_status' => $user->account_status,
        ]);

        /*
         * Redirect to the intended page or admin dashboard.
         */
        return redirect()->intended(
            route('admin.dashboard')
        );
    }

    /**
     * Log the administrator out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Log::info('ADMIN LOGOUT', [
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
        ]);

        Auth::logout();

        /*
         * Invalidate the current session.
         */
        $request->session()->invalidate();

        /*
         * Generate a new CSRF token.
         */
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
