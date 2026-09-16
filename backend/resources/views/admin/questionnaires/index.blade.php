@extends('layouts.admin')
@section('title', 'Questionnaire Management')
@section('body')
@php($retention = \App\Models\Questionnaire::TRASH_RETENTION_DAYS)
@php($liveCount = $versions->where('is_active', true)->count())
@php($draftCount = $versions->where('status', 'draft')->count())
@php($archivedCount = $versions->where('status', 'archived')->count())
<main class="content stack">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif

    <div class="questionnaire-workspace-grid">
        <div class="questionnaire-main-column">
             <div class="questionnaire-heading">
                <div>
                    <h2>Questionnaire Management</h2>
                    <p class="lede">Create, organise, configure, review and publish wellbeing questionnaires.</p>
                </div>
            </div>

            @include('admin.questionnaires._tabs')
            <section class="questionnaire-summary" aria-label="Questionnaire summary">
                <div class="card questionnaire-summary-card"><span class="summary-icon"><i data-lucide="files"></i></span><span class="k">Total questionnaires</span><strong>{{ $versions->count() }}</strong><span class="muted">All saved versions</span></div>
                <div class="card questionnaire-summary-card"><span class="summary-icon sage"><i data-lucide="radio"></i></span><span class="k">Live</span><strong>{{ $liveCount }}</strong><span class="muted">Currently active</span></div>
                <div class="card questionnaire-summary-card"><span class="summary-icon mauve"><i data-lucide="pencil-line"></i></span><span class="k">Draft</span><strong>{{ $draftCount }}</strong><span class="muted">Still in progress</span></div>
                <div class="card questionnaire-summary-card"><span class="summary-icon blush"><i data-lucide="archive"></i></span><span class="k">Archived</span><strong>{{ $archivedCount }}</strong><span class="muted">Not active</span></div>
                <a class="card questionnaire-summary-card questionnaire-bank-card" href="{{ route('admin.questions.index') }}"><span class="summary-icon teal"><i data-lucide="book-open"></i></span><span class="k">Questions in bank</span><strong>{{ isset($questionBankCount) ? $questionBankCount : '—' }}</strong><span class="muted">Open Question Bank</span></a>
            </section>

            <section class="panel questionnaire-list-panel">
                <div class="split panel-head">
                    <div>
                        <h2>All Questionnaires ({{ $versions->count() }})</h2>
                        <p class="lede">Only one questionnaire can be live at a time. Publishing one will turn other versions into drafts.</p>
                    </div>
                    <div class="questionnaire-list-tools">
                        <label class="questionnaire-search" for="questionnaire-search"><i data-lucide="search"></i><input id="questionnaire-search" type="search" placeholder="Search questionnaires..." data-questionnaire-search></label>
                        <button class="button button-secondary questionnaire-filter" type="button" data-questionnaire-filter aria-pressed="false"><i data-lucide="sliders-horizontal"></i>Filter</button>
                    </div>
                </div>
                <div class="questionnaire-table" data-questionnaire-list>
                    <div class="questionnaire-table-head" aria-hidden="true"><span>Version</span><span>Title</span><span>Content Summary</span><span>Status</span><span>Last Updated</span><span>Actions</span></div>
                    @forelse($versions as $item)
                        @php($idot = $item->is_active ? 'dot-live' : ($item->status === 'draft' ? 'dot-draft' : 'dot-archived'))
                        <div class="questionnaire-row" data-questionnaire-row data-status="{{ $item->is_active ? 'live' : $item->status }}" data-search="{{ strtolower($item->title . ' ' . $item->status . ' v' . $item->version) }}">
                            <span class="state-line"><span class="status-dot {{ $idot }}"></span>v{{ $item->version }}</span>
                            <div class="questionnaire-title-cell"><span class="item-title">{{ $item->title }}</span><small>Version {{ $item->version }}</small></div>
                            <div class="questionnaire-summary-cell"><span><i data-lucide="list"></i>{{ $item->sections_count }} sections</span><span><i data-lucide="list-checks"></i>{{ $item->questions_count }} questions</span><span><i data-lucide="circle-dot"></i>{{ $item->score_bands_count }} ranges</span></div>
                            <span><span class="badge {{ $item->is_active && ! $item->isScheduled() ? 'active' : '' }}">{{ $item->publishState() }}</span></span>
                            <div class="questionnaire-updated"><strong>{{ $item->updated_at?->diffForHumans() }}</strong><small>by SheZen Demo Admin</small></div>
                            <div class="actions questionnaire-row-actions">
                                @unless($item->is_active)<a class="button" href="{{ route('admin.questionnaires.review', $item) }}">Review &amp; publish</a>@endunless
                                <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $item) }}"><i data-lucide="pencil"></i>Edit</a>
                                <details class="more">
                                    <summary class="button button-link" aria-label="More actions for {{ $item->title }} v{{ $item->version }}"><i data-lucide="ellipsis"></i></summary>
                                    <div class="more-menu">
                                        <a href="{{ route('admin.questionnaires.scoring', $item) }}">Scoring</a>
                                        @if($item->status !== 'archived' && ! $item->is_active)<form method="POST" action="{{ route('admin.questionnaires.archive', $item) }}">@csrf @method('PATCH')<button type="submit">Archive</button></form>@endif
                                        <hr>
                                        <form method="POST" action="{{ route('admin.questionnaires.destroy', $item) }}" onsubmit="return confirm('Delete “{{ $item->title }}” v{{ $item->version }}?\n\nIt moves to Trash and is permanently removed after {{ $retention }} days unless restored. Versions with student results are archived instead.')">@csrf @method('DELETE')<button type="submit" class="danger">Delete…</button></form>
                                    </div>
                                </details>
                            </div>
                        </div>
                    @empty
                        <p class="lede empty-state">No questionnaires yet. Add one to build your SheZen wellbeing assessment — its sections, questions and result ranges are all set up in the editor.</p>
                    @endforelse
                </div>
                <p class="lede questionnaire-filter-empty" data-questionnaire-empty hidden>No questionnaires match your search or filter.</p>
            </section>
        </div>
        <aside class="questionnaire-helper stack-sm">
            <section class="panel">
                <h2><i data-lucide="zap"></i>Quick Actions</h2>
                <div class="quick-actions questionnaire-quick-actions">
                    <a class="quick-action primary" href="{{ route('admin.questionnaires.create') }}"><i data-lucide="plus"></i><span>Add a questionnaire</span></a>
                    <a class="quick-action" href="{{ route('admin.questions.index') }}"><i data-lucide="library"></i><span>Open Question Bank</span><i data-lucide="arrow-up-right"></i></a>
                    @if($versions->firstWhere('is_active', true) ?? $versions->first())
                        @php($previewQuestionnaire = $versions->firstWhere('is_active', true) ?? $versions->first())
                        <a class="quick-action" href="{{ route('admin.questionnaires.preview', $previewQuestionnaire) }}"><i data-lucide="eye"></i><span>Preview active questionnaire</span><i data-lucide="arrow-up-right"></i></a>
                    @else
                        <a class="quick-action" href="{{ route('admin.questionnaires.create') }}"><i data-lucide="eye"></i><span>Preview active questionnaire</span><i data-lucide="arrow-up-right"></i></a>
                    @endif
                    <a class="quick-action" href="{{ route('admin.questionnaires.trash') }}"><i data-lucide="trash-2"></i><span>Open Trash</span><i data-lucide="arrow-up-right"></i></a>
                </div>
            </section>
            <section class="panel workflow-card">
                <h2><i data-lucide="grid-2x2"></i>Questionnaire Workflow</h2>
                <ol>
                    <li><span>1</span><div><a href="{{ $versions->first() ? route('admin.questionnaires.show-details', $versions->first()) : route('admin.questionnaires.create') }}">Details</a><small>Set title, description and basic configuration</small></div></li>
                    <li><span>2</span><div><a href="{{ $versions->first() ? route('admin.questionnaires.sections.index', $versions->first()) : route('admin.questionnaires.create') }}">Sections &amp; Questions</a><small>Organise sections and add questions</small></div></li>
                    <li><span>3</span><div><a href="{{ $versions->first() ? route('admin.questionnaires.scoring', $versions->first()) : route('admin.questionnaires.create') }}">Scoring</a><small>Configure score ranges and support content</small></div></li>
                    <li><span>4</span><div><a href="{{ $versions->first() ? route('admin.questionnaires.review', $versions->first()) : route('admin.questionnaires.create') }}">Review &amp; Publish</a><small>Validate and publish when ready</small></div></li>
                </ol>
            </section>
        </aside>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.querySelector('[data-questionnaire-search]');
    const filter = document.querySelector('[data-questionnaire-filter]');
    const rows = Array.from(document.querySelectorAll('[data-questionnaire-row]'));
    const empty = document.querySelector('[data-questionnaire-empty]');
    let draftsOnly = false;
    function updateRows() {
        const query = (search?.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const visibleRow = (!query || row.dataset.search.includes(query)) && (!draftsOnly || row.dataset.status === 'draft');
            row.hidden = !visibleRow;
            if (visibleRow) visible++;
        });
        if (empty) empty.hidden = visible !== 0;
    }
    search?.addEventListener('input', updateRows);
    filter?.addEventListener('click', function () {
        draftsOnly = !draftsOnly;
        filter.setAttribute('aria-pressed', String(draftsOnly));
        filter.classList.toggle('is-selected', draftsOnly);
        updateRows();
    });
});
</script>
@endsection
