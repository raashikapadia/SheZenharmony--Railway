@extends('layouts.admin')
@section('title', 'Questionnaires')
@section('body')
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel">
        <div class="page-intro"><div><h2>Assessment Manager</h2><p class="muted">Manage drafts, published versions, questions, and scoring ranges.</p></div><a class="button" href="{{ route('admin.questionnaires.create') }}"><span>＋</span>New questionnaire</a></div>
        <div class="table-wrap"><table><thead><tr><th>Questionnaire</th><th>Version</th><th>Status</th><th>Questions</th><th>Bands</th><th>Actions</th></tr></thead><tbody>
        @forelse($questionnaires as $item)
            <tr><td><div class="item-title">{{ $item->title }}</div><span class="muted">{{ $item->description ?: 'No description' }}</span></td><td>{{ ucfirst($item->type) }} v{{ $item->version }}</td><td><span class="badge {{ $item->is_active ? 'active' : '' }}">{{ $item->status }}{{ $item->is_active ? ' · live' : '' }}</span></td><td>{{ $item->questions_count }}</td><td>{{ $item->score_bands_count }}</td><td><div class="actions"><a class="button button-secondary" href="{{ route('admin.questionnaires.edit', $item) }}">Edit</a><form method="POST" action="{{ route('admin.questionnaires.destroy', $item) }}" onsubmit="return confirm('Archive this questionnaire?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Archive</button></form></div></td></tr>
        @empty<tr><td colspan="6">No questionnaires yet.</td></tr>@endforelse
        </tbody></table></div>
        <div style="margin-top:18px">{{ $questionnaires->links() }}</div>
    </section>
</main>
@endsection
