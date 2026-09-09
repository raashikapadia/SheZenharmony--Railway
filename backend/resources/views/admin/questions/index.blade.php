@extends('layouts.admin')
@section('title', 'Question Builder')
@section('body')
<main class="content">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaires</a>
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel" style="margin-top:16px">
        <div class="page-intro"><div><h2>Questions</h2><p class="muted">The reusable question bank. Add, edit, activate, or retire questions, then attach them to a questionnaire and its sections.</p></div><a class="button" href="{{ route('admin.questions.create') }}"><span>＋</span>Add question</a></div>
        <div class="table-wrap"><table><thead><tr><th>#</th><th>Question</th><th>Dimension</th><th>Options</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse($questions as $question)
            <tr><td>{{ $question->position }}</td><td><div class="item-title">{{ $question->question_text }}</div>@if($question->is_sensitive)<span class="muted">Sensitive content</span>@endif</td><td>{{ $question->dimension ?: '—' }}</td><td>{{ $question->options->count() }}</td><td><span class="badge {{ $question->is_active ? 'active' : '' }}">{{ $question->is_active ? 'Active' : 'Inactive' }}</span></td><td><div class="actions"><a class="button button-secondary" href="{{ route('admin.questions.edit', $question) }}">Edit</a><form method="POST" action="{{ route('admin.questions.destroy', $question) }}" onsubmit="return confirm('Delete this question?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td></tr>
        @empty<tr><td colspan="6">No questions configured.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</main>
@endsection
