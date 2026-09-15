@extends('layouts.admin')
@section('title', 'Games & Quizzes')
@section('body')
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <div class="page-intro">
        <div><h1>Games & Quizzes</h1><p class="lede">Manage quizzes independently from the existing student games.</p></div>
        <a class="button" href="{{ route('admin.positive-engagement.games-quizzes.create') }}">+ Add quiz</a>
    </div>
    <div class="cards" style="margin-bottom:20px">
        <article class="card"><div class="muted">Total Games</div><div class="metric">Existing</div><div class="metric-note">Existing games are unchanged</div></article>
        <article class="card"><div class="muted">Total Quizzes</div><div class="metric">{{ $stats['totalQuizzes'] }}</div></article>
        <article class="card"><div class="muted">Active Quizzes</div><div class="metric">{{ $stats['activeQuizzes'] }}</div></article>
        <article class="card"><div class="muted">Quiz Questions</div><div class="metric">{{ $stats['totalQuestions'] }}</div></article>
        <article class="card"><div class="muted">Total Completions</div><div class="metric">{{ $stats['totalCompletions'] }}</div></article>
    </div>
    <section class="panel">
        <div class="panel-head"><h2>Quiz library</h2><p class="muted">Create, edit, publish, or remove quizzes and their questions.</p></div>
        <div class="table-wrap"><table><thead><tr><th>Name</th><th>Category</th><th>Status</th><th>Questions</th><th>Times Used</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
        @forelse($quizzes as $quiz)
            <tr><td><div class="item-title">{{ $quiz->name }}</div><span class="muted">{{ Str::limit($quiz->description, 70) }}</span></td><td>{{ $quiz->category }}</td><td><span class="badge {{ $quiz->status === 'active' ? 'active' : '' }}">{{ ucfirst($quiz->status) }}</span></td><td>{{ $quiz->questions_count }}</td><td>{{ $quiz->attempts_count }}</td><td>{{ $quiz->updated_at?->format('d M Y') }}</td><td><div class="actions"><a class="button button-secondary" href="{{ route('admin.positive-engagement.games-quizzes.edit', $quiz) }}">Edit</a><form method="POST" action="{{ route('admin.positive-engagement.games-quizzes.destroy', $quiz) }}" onsubmit="return confirm('Delete this quiz and its questions and completion records?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td></tr>
        @empty
            <tr><td colspan="7">No quizzes configured.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
    <section class="panel" style="margin-top:20px">
        <div class="panel-head"><h2>Student completion records</h2><p class="muted">Students are identified by their pseudonymous ID. Admin views and edits are not counted.</p></div>
        <div class="table-wrap"><table><thead><tr><th>Quiz</th><th>Student ID</th><th>Score</th><th>Completed</th></tr></thead><tbody>
        @php($hasAttempts = false)
        @foreach($quizzes as $quiz)
            @foreach($quiz->attempts as $attempt)
                @php($hasAttempts = true)
                <tr><td>{{ $quiz->name }}</td><td>{{ $attempt->studentIdentity?->displayId() ?? 'Unavailable' }}</td><td>{{ $attempt->score === null ? 'Not recorded' : $attempt->score }}</td><td>{{ $attempt->completed_at?->format('d M Y H:i') }}</td></tr>
            @endforeach
        @endforeach
        @if(!$hasAttempts)<tr><td colspan="4">No student quiz completions recorded yet.</td></tr>@endif
        </tbody></table></div>
    </section>
</main>
@endsection
