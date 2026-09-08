@extends('layouts.admin')
@section('title', 'Shezen Chat Buddy')
@section('body')
<main class="content">
    <section class="panel stack-sm">
        <div class="split">
            <div>
                <h1>Shezen Chat Buddy</h1>
                <p class="lede">Everything Shezen says is written here. Shezen is strictly rule based &mdash; it matches a student's words against the keywords below and replies with the message you wrote. It never generates language of its own.</p>
            </div>
            <a class="button" href="{{ route('admin.chatbuddy.create') }}">+ Add category</a>
        </div>

        <p class="muted" style="font-size:.85rem">The student experience is currently shown as <strong>Coming Soon</strong> in the app. Content you add now will be ready when it is switched on.</p>

        @if(session('status'))<p class="badge active">{{ session('status') }}</p>@endif
    </section>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Keywords</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($intents as $intent)
                <tr>
                    <td>
                        <div class="item-title">
                            {{ $intent->name }}
                            @if($intent->is_crisis)<span class="badge">Safety</span>@endif
                            @if($intent->is_starter)<span class="badge">Starter</span>@endif
                        </div>
                        <span class="muted">{{ $intent->code }}</span>
                    </td>
                    <td><span class="muted">{{ Str::limit($intent->keywords, 70) ?: '—' }}</span></td>
                    <td>{{ $intent->priority }}</td>
                    <td><span class="badge {{ $intent->is_active ? 'active' : '' }}">{{ $intent->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        <div class="actions">
                            <a class="button button-secondary" href="{{ route('admin.chatbuddy.edit', $intent) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.chatbuddy.destroy', $intent) }}" onsubmit="return confirm('Delete this category and its reply?')">
                                @csrf @method('DELETE')
                                <button class="button button-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No categories yet. Add one to give Shezen something to say.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
</main>
@endsection
