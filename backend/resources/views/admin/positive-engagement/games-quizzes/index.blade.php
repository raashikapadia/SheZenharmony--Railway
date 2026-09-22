@extends('layouts.admin')
@section('title', 'Quizzes')
@section('body')
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <div class="page-intro">
        <div><h1>Quizzes</h1><p class="lede">Manage quizzes independently from the existing student games.</p></div>
        <a class="button" href="{{ route('admin.positive-engagement.games-quizzes.create') }}">+ Add quiz</a>
    </div>
    <div class="cards" style="margin-bottom:20px">
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
    <details class="panel" style="margin-top:20px;padding:0;overflow:hidden">
        <summary style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;cursor:pointer;list-style:none">
            <span><strong>Recent student activity</strong><br><span class="muted">Latest results by quiz. Open to review details.</span></span>
            <span class="badge active">{{ $stats['totalCompletions'] }} total</span>
        </summary>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;padding:0 20px 20px">
            @forelse($quizzes as $quiz)
                <article style="border:1px solid #d8e5f2;border-radius:12px;padding:14px;background:#f8fbfe">
                    <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:10px">
                        <strong>{{ $quiz->name }}</strong>
                        <span class="muted">{{ $quiz->attempts_count }} plays</span>
                    </div>
                    @forelse($quiz->attempts->take(3) as $attempt)
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 0;border-top:1px solid #e4edf5;font-size:.86rem">
                            <span>{{ $attempt->studentIdentity?->displayId() ?? 'Unavailable' }}</span>
                            <span><strong>Score: {{ $attempt->score === null ? '—' : $attempt->score.'/'.$quiz->questions_count }}</strong> <span class="muted">{{ $attempt->completed_at?->format('d M H:i') }}</span></span>
                        </div>
                    @empty
                        <span class="muted">No student completions yet.</span>
                    @endforelse
                </article>
            @empty
                <p class="muted">No quizzes configured.</p>
            @endforelse
        </div>
    </details>
</main>
@endsection
