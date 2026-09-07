@extends('layouts.admin')
@section('title', 'Questionnaire Management')
@section('body')
@php($retention = \App\Models\Questionnaire::TRASH_RETENTION_DAYS)
<main class="content stack">
    @include('admin.questionnaires._tabs')

    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif

    <section class="panel">
        <div class="split panel-head">
            <div>
                <h2>All questionnaires ({{ $versions->count() }})</h2>
                <p class="lede">Only one is live at a time. Publishing one turns every other version into a draft.</p>
            </div>
            <div class="actions">
                <a class="button button-secondary" href="{{ route('admin.questionnaires.trash') }}">Trash ({{ $trashCount ?? 0 }})</a>
                <a class="button" href="{{ route('admin.questionnaires.create') }}"><span>＋</span>Add a questionnaire</a>
            </div>
        </div>

        @forelse($versions as $item)
            @php($idot = $item->is_active ? 'dot-live' : ($item->status === 'draft' ? 'dot-draft' : 'dot-archived'))
            <div class="version-row">
                <span class="state-line"><span class="status-dot {{ $idot }}"></span>v{{ $item->version }}</span>
                <div class="grow">
                    <span class="item-title">{{ $item->title }}</span>
                    <span class="badge {{ $item->is_active ? 'active' : '' }}" style="margin-left:8px">{{ $item->is_active ? 'Live' : ucfirst($item->status) }}</span>
                    <div class="meta">{{ $item->sections_count }} sections · {{ $item->questions_count }} questions · {{ $item->score_bands_count }} ranges · updated {{ $item->updated_at?->diffForHumans() }}</div>
                </div>
                <div class="actions">
                    @unless($item->is_active)
                        <form method="POST" action="{{ route('admin.questionnaires.publish', $item) }}">@csrf @method('PATCH')<button class="button" type="submit">Publish now</button></form>
                    @endunless
                    <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $item) }}">Edit</a>
                    <details class="more">
                        <summary class="button button-link">⋯</summary>
                        <div class="more-menu">
                            <a href="{{ route('admin.questionnaires.preview', $item) }}">Preview</a>
                            <a href="{{ route('admin.questionnaires.scoring', $item) }}">Scoring</a>
                            @if($item->status !== 'archived' && ! $item->is_active)
                                <form method="POST" action="{{ route('admin.questionnaires.archive', $item) }}">@csrf @method('PATCH')<button type="submit">Archive</button></form>
                            @endif
                            <hr>
                            <form method="POST" action="{{ route('admin.questionnaires.destroy', $item) }}" onsubmit="return confirm('Delete “{{ $item->title }}” v{{ $item->version }}?\n\nIt moves to Trash and is permanently removed after {{ $retention }} days unless restored. Versions with student results are archived instead.')">@csrf @method('DELETE')<button type="submit" class="danger">Delete…</button></form>
                        </div>
                    </details>
                </div>
            </div>
        @empty
            <p class="lede">No questionnaires yet. Add one to build your SheZen wellbeing assessment — its sections, questions and result ranges are all set up in the editor.</p>
        @endforelse
    </section>
</main>
@endsection
