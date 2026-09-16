@extends('layouts.admin')

@section('title', 'Admin sign in')

@section('body')
<main class="admin-login-shell">
    <section class="admin-login-card" aria-labelledby="admin-login-title">
        <div class="admin-login-form-pane">
            <div class="admin-login-content">
                <img class="admin-login-floral-logo" src="{{ asset('images/admin-login-floral-logo.png') }}" alt="SheZen Harmony">
                <h1 id="admin-login-title">Admin Portal Login</h1>
                <p class="admin-login-subtitle">Sign in to manage the SheZen Harmony platform</p>

                <form class="admin-login-form" method="POST" action="{{ route('admin.login.store') }}">
                    @csrf
                    <label for="email">Email address</label>
                    <div class="admin-login-input-wrap">
                        <span class="admin-login-field-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="m4 7 8 6 8-6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg>
                        </span>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="Enter your email address" required autofocus>
                    </div>
                    @error('email')<div class="error">{{ $message }}</div>@enderror

                    <label for="password">Password</label>
                    <div class="admin-login-input-wrap">
                        <span class="admin-login-field-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/><circle cx="12" cy="15" r="1.2" fill="currentColor"/></svg>
                        </span>
                        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
                        <button id="admin-password-toggle" class="admin-login-password-icon" type="button" aria-label="Show password" aria-controls="password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.4-5 9.5-5 9.5 5 9.5 5-3.4 5-9.5 5-9.5-5-9.5-5Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/><circle cx="12" cy="12" r="2.3" stroke="currentColor" stroke-width="1.8"/><path class="admin-login-eye-slash" d="M4 20 20 4" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/></svg>
                        </button>
                    </div>
                    @error('password')<div class="error">{{ $message }}</div>@enderror

                    <div class="admin-login-options">
                        <label class="admin-login-remember"><input name="remember" type="checkbox" value="1"> Keep me signed in</label>
                    </div>
                    <button class="button admin-login-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
                </form>
                <footer class="admin-login-footer">SheZen Harmony <span aria-hidden="true">|</span><em>Supporting Wellbeing at Work</em></footer>
            </div>
        </div>

        <aside class="admin-login-visual-pane" aria-label="Administrator dashboard illustration">
            <span class="admin-login-orb admin-login-orb-one" aria-hidden="true"></span>
            <span class="admin-login-orb admin-login-orb-two" aria-hidden="true"></span>
            <img class="admin-login-illustration" src="{{ asset('images/admin-login-workspace-illustration-transparent.png') }}" alt="Administrator working at a wellbeing platform desk">
        </aside>
    </section>
</main>
<script>
document.getElementById('admin-password-toggle').addEventListener('click', function () {
    const password = document.getElementById('password');
    const showPassword = password.type === 'password';
    password.type = showPassword ? 'text' : 'password';
    this.setAttribute('aria-pressed', String(showPassword));
    this.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
});
</script>
@endsection
