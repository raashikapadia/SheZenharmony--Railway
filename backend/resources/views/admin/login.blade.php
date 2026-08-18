@extends('layouts.admin')

@section('title', 'Admin sign in')

@section('body')
<main class="auth-wrap">
    <section class="auth-card">
        <div class="brand">SheZen Harmony</div>
        <h1>Administrator sign in</h1>
        <p class="muted">Use an account with the administrator role.</p>

        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email')<div class="error">{{ $message }}</div>@enderror

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password')<div class="error">{{ $message }}</div>@enderror

            <label class="remember"><input name="remember" type="checkbox" value="1"> Keep me signed in</label>
            <button class="button" type="submit">Sign in</button>
        </form>
    </section>
</main>
@endsection
