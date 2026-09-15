@extends('layouts.admin')
@section('title', 'Versions · Questionnaire Management')
@section('body')
@php($retention = \App\Models\Questionnaire::TRASH_RETENTION_DAYS)
<main class="content stack">
    @include('admin.questionnaires._tabs')
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif

    <div class="split">
        <div>
            <h1 style="margin:0 0 4px">Versions</h1>
            <p class="lede">Editing a draft never changes what students are currently taking. Publish a draft to make it live. Deleted versions rest in Trash for {{ $retention }} days.</p>
        </div>
        <div class="actions">
            <a class="button button-secondary" href="{{ route('admin.questionnaires.trash') }}">Trash ({{ $trashCount ?? 0 }})</a>
            <a class="button" href="{{ route('admin.questionnaires.create') }}"><span>＋</span>New questionnaire</a>
        </div>
    </div>

    <section class="panel">
        @forelse($versions as $item)
            @php($idot = $item->is_active ? 'dot-live' : ($item->status === 'draft' ? 'dot-draft' : 'dot-archived'))
            <div class="version-row">
                <span class="state-line"><span class="status-dot {{ $idot }}"></span>v{{ $item->version }}</span>
                <div class="grow">
                    <span class="badge {{ $item->is_active ? 'active' : '' }}">{{ $item->is_active ? 'Live' : ucfirst($item->status) }}</span>
                    <div class="meta">{{ $item->sections_count }} sections · {{ $item->questions_count }} questions · {{ $item->score_bands_count }} ranges · updated {{ $item->updated_at?->diffForHumans() }}</div>
                </div>
                <div class="actions">
                    @unless($item->is_active)
                        <a class="button" href="{{ route('admin.questionnaires.review', $item) }}">Review &amp; publish</a>
                    @endunless
                    <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $item) }}">{{ $item->status === 'archived' ? 'View' : 'Edit' }}</a>
                    <details class="more">
                        <summary class="button button-link">⋯</summary>
                        <div class="more-menu">
                            <a href="{{ route('admin.questionnaires.preview', $item) }}">Preview</a>
                            <a href="{{ route('admin.questionnaires.scoring', $item) }}">Scoring</a>
                            @if($item->status !== 'archived' && ! $item->is_active)
                                <form method="POST" action="{{ route('admin.questionnaires.archive', $item) }}">@csrf @method('PATCH')<button type="submit">Archive</button></form>
                            @endif
                            <hr>
                            <form method="POST" action="{{ route('admin.questionnaires.destroy', $item) }}" onsubmit="return confirm('Delete “{{ $item->title }}” v{{ $item->version }}?\n\nIt moves to Trash and is permanently removed after {{ $retention }} days unless restored. Versions with student results are archived instead — this cannot be undone once purged.')">@csrf @method('DELETE')<button type="submit" class="danger">Delete…</button></form>
                        </div>
                    </details>
                </div>
            </div>
        @empty
            <p class="lede">No versions yet.</p>
        @endforelse
    </section>
</main>
@endsection
