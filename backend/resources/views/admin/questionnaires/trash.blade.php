@extends('layouts.admin')
@section('title', 'Trash · Questionnaires')
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaire Management</a>
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif

    <div>
        <h1 style="margin:0 0 4px">Trash</h1>
        <p class="lede">Deleted questionnaires are kept here for {{ $retentionDays }} days, then permanently removed. Restore one to bring it back (it returns as <em>archived</em>).</p>
    </div>

    <section class="panel">
        <div class="table-wrap"><table>
            <thead><tr><th>Questionnaire</th><th>Version</th><th>Deleted</th><th>Permanently deleted</th><th>Sections / Questions</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($questionnaires as $item)
                @php($daysLeft = $item->purge_after ? now()->diffInDays($item->purge_after, false) : null)
                <tr>
                    <td><div class="item-title">{{ $item->title }}</div><span class="muted">{{ $item->description ?: 'No description' }}</span></td>
                    <td>v{{ $item->version }}</td>
                    <td>{{ $item->trashed_at?->diffForHumans() ?? '—' }}</td>
                    <td>
                        {{ $item->purge_after?->toFormattedDateString() ?? '—' }}
                        @if($daysLeft !== null)<br><span class="muted">{{ $daysLeft <= 0 ? 'due now' : 'in '.ceil($daysLeft).' day'.(ceil($daysLeft) === 1.0 ? '' : 's') }}</span>@endif
                    </td>
                    <td>{{ $item->sections_count }} / {{ $item->questions_count }}</td>
                    <td><div class="actions">
                        <form method="POST" action="{{ route('admin.questionnaires.restore', $item->id) }}">@csrf @method('PATCH')<button class="button button-secondary" type="submit">Restore</button></form>
                        <form method="POST" action="{{ route('admin.questionnaires.force-destroy', $item->id) }}" onsubmit="return confirm('⚠ Permanently delete “{{ $item->title }}” now?\n\nThis cannot be undone.')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete permanently</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6">Trash is empty.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div style="margin-top:16px">{{ $questionnaires->links() }}</div>
    </section>
</main>
@endsection
