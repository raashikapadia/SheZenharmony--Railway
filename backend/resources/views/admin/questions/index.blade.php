@extends('layouts.admin')
@section('title', 'Questions')
@section('body')
<div class="shell"><header class="topbar"><nav class="nav"><a href="{{ route('admin.dashboard') }}">Dashboard</a><span class="brand">Questions</span><a href="{{ route('admin.interventions.index') }}">Interventions</a></nav><a class="button button-secondary" href="{{ route('admin.questions.create') }}">Add question</a></header>
<main class="content">@if(session('status'))<div class="status">{{ session('status') }}</div>@endif
<h1>Questionnaire management</h1><p class="muted">Active changes are returned by the mobile API immediately.</p>
<div class="table-wrap"><table><thead><tr><th>Position</th><th>Question</th><th>Dimension</th><th>Options</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($questions as $question)<tr><td>{{ $question->position }}</td><td>{{ $question->question_text }} @if($question->is_sensitive)<strong>(sensitive)</strong>@endif</td><td>{{ $question->dimension ?: '—' }}</td><td>{{ $question->options->count() }}</td><td>{{ $question->is_active ? 'Active' : 'Inactive' }}</td><td><div class="actions"><a class="button button-secondary" href="{{ route('admin.questions.edit', $question) }}">Edit</a><form method="POST" action="{{ route('admin.questions.destroy', $question) }}" onsubmit="return confirm('Delete this question?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td></tr>@empty<tr><td colspan="6">No questions configured.</td></tr>@endforelse
</tbody></table></div>{{ $questions->links() }}</main></div>
@endsection
