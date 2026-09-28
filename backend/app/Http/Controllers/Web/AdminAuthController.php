<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (RateLimiter::tooManyAttempts($loginKey, 3)) {
            $seconds = RateLimiter::availableIn($loginKey);

            return back()
                ->withErrors([
                    'email' => 'Too many failed login attempts. Please try again in 5 minutes.',
                ])
                ->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($loginKey, 300);

            return back()
                ->withErrors([
                    'email' => 'The supplied administrator credentials are incorrect.',
                ])
                ->onlyInput('email');
        }

        if (! Auth::user()?->isAdmin() || Auth::user()?->account_status !== 'active') {
            Auth::logout();

            RateLimiter::hit($loginKey, 300);

            return back()
                ->withErrors([
                    'email' => 'The supplied administrator credentials are incorrect.',
                ])
                ->onlyInput('email');
        }

        // Successful admin login clears the failed-attempt counter.
        RateLimiter::clear($loginKey);

        $request->session()->regenerate();

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