@extends('layouts.admin')
@section('title', 'Questionnaire Management')
@section('body')
@php($retention = \App\Models\Questionnaire::TRASH_RETENTION_DAYS)
@php($liveCount = $versions->where('is_active', true)->count())
@php($draftCount = $versions->where('status', 'draft')->count())
@php($archivedCount = $versions->where('status', 'archived')->count())
<main class="content stack questionnaire-home">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif

    <div class="questionnaire-workspace-grid">
        <div class="questionnaire-main-column">
             <div class="questionnaire-heading">
                <div>
                    <h2>Questionnaire Management</h2>
                    <p class="lede">Create, organise, configure, review and publish wellbeing questionnaires.</p>
                </div>
            </div>

            <section class="questionnaire-hero" aria-label="Create a new questionnaire">
                <div class="questionnaire-hero-copy">
                    <span class="questionnaire-eyebrow">Start here</span>
                    <h2>Create a New Questionnaire</h2>
                    <p>Build a personalised wellbeing questionnaire with custom sections, questions and scoring. Get started in just a few steps.</p>
                    <a class="questionnaire-create" href="{{ route('admin.questionnaires.create') }}"><i data-lucide="plus"></i>Create Questionnaire<i data-lucide="chevron-right"></i></a>
                    <div class="questionnaire-hero-links"><a href="{{ route('admin.questions.index') }}"><i data-lucide="library"></i>Open Question Bank</a><a href="#existing-questionnaires" data-show-drafts><i data-lucide="file-text"></i>View Drafts</a></div>
                </div>
                <div class="questionnaire-hero-art" aria-hidden="true">
                    <svg viewBox="0 0 270 220" fill="none"><path d="M89 220C118 170 153 109 143 14M92 220C74 177 47 150 8 142M121 170C154 151 193 137 238 147" stroke="#90bab9" stroke-width="2"/><path d="M143 14C128 38 132 53 143 66C154 48 154 34 143 14ZM131 89C119 63 102 55 80 52C91 77 105 89 131 89ZM154 122C158 96 176 81 199 76C198 100 181 118 154 122ZM90 153C72 131 51 126 27 130C44 151 64 160 90 153ZM186 144C207 123 230 119 251 125C231 144 211 151 186 144ZM75 183C52 168 34 173 13 185C36 197 57 196 75 183Z" fill="#a7cecd" opacity=".8"/></svg>
                    <div class="questionnaire-art-paper paper-back"></div><div class="questionnaire-art-paper paper-front"><span></span><strong>Wellbeing<br>Questionnaire</strong><i></i><i></i><i></i></div><em>Small<br>questions.<br>Brighter<br>tomorrows.</em>
                </div>
            </section>
            <nav class="questionnaire-journey" id="questionnaire-workflow" aria-label="Questionnaire creation steps">
                <a href="{{ route('admin.questionnaires.create') }}"><b>1</b><i data-lucide="files"></i><span><strong>Create Questionnaire</strong><small>Set title, description<br>and basic configuration</small></span></a>
                <a href="{{ $versions->first() ? route('admin.questionnaires.sections.index', $versions->first()) : route('admin.questionnaires.create') }}"><b>2</b><i data-lucide="list"></i><span><strong>Add Sections &amp; Questions</strong><small>Organise sections<br>and add questions</small></span></a>
                <a href="{{ $versions->first() ? route('admin.questionnaires.scoring', $versions->first()) : route('admin.questionnaires.create') }}"><b>3</b><i data-lucide="chart-no-axes-combined"></i><span><strong>Configure Scoring</strong><small>Set score ranges and<br>support content</small></span></a>
                <a href="{{ $versions->first() ? route('admin.questionnaires.review', $versions->first()) : route('admin.questionnaires.create') }}"><b>4</b><i data-lucide="circle-check"></i><span><strong>Review &amp; Publish</strong><small>Validate and publish<br>when ready</small></span></a>
            </nav>
            <section class="panel questionnaire-list-panel" id="existing-questionnaires">
                <div class="split panel-head">
                    <div>
                        <h2>Continue Existing Work</h2>
                        <p class="lede">Pick up where you left off or manage your existing questionnaires.</p>
                    </div>
                    <div class="questionnaire-list-tools">
                        <label class="questionnaire-search" for="questionnaire-search"><i data-lucide="search"></i><input id="questionnaire-search" type="search" placeholder="Search questionnaires..." data-questionnaire-search></label>
                        <button class="button button-secondary questionnaire-filter" type="button" data-questionnaire-filter aria-pressed="false"><i data-lucide="filter"></i>Filter</button>
                    </div>
                </div>
                <div class="questionnaire-status-tabs" role="tablist" aria-label="Filter questionnaires by status">
                    <button type="button" role="tab" aria-selected="true" data-status-tab="all">All ({{ $versions->count() }})</button>
                    <button type="button" role="tab" aria-selected="false" data-status-tab="draft">Drafts ({{ $draftCount }})</button>
                    <button type="button" role="tab" aria-selected="false" data-status-tab="live">Live ({{ $liveCount }})</button>
                    <button type="button" role="tab" aria-selected="false" data-status-tab="archived">Archived ({{ $archivedCount }})</button>
                </div>
                <div class="questionnaire-table" data-questionnaire-list>
                    <div class="questionnaire-table-head" aria-hidden="true"><span>Version</span><span>Title</span><span>Content Summary</span><span>Status</span><span>Last Updated</span><span>Actions</span></div>
                    @forelse($versions as $item)
                        @php($idot = $item->is_active ? 'dot-live' : ($item->status === 'draft' ? 'dot-draft' : 'dot-archived'))
                        <div class="questionnaire-row" data-questionnaire-row data-status="{{ $item->is_active ? 'live' : $item->status }}" data-search="{{ strtolower($item->title . ' ' . $item->status . ' v' . $item->version) }}">
                            <span class="questionnaire-row-icon {{ $item->is_active ? 'live' : '' }}"><i data-lucide="{{ $item->is_active ? 'activity' : 'pencil-line' }}"></i></span>
                            <div class="questionnaire-title-cell"><span class="item-title">{{ $item->title }}</span><small>Version {{ $item->version }} &nbsp;•&nbsp; {{ $item->sections_count }} sections &nbsp;•&nbsp; {{ $item->questions_count }} questions</small></div>
                            <span><span class="badge {{ $item->is_active && ! $item->isScheduled() ? 'active' : '' }}"><span class="status-dot {{ $idot }}"></span>{{ $item->publishState() }}</span></span>
                            <div class="questionnaire-updated"><small>Last updated</small><strong>{{ $item->updated_at?->diffForHumans() }}</strong><small>by {{ auth()->user()->name }}</small></div>
                            <div class="actions questionnaire-row-actions">
                                @unless($item->is_active)<a class="button" href="{{ route('admin.questionnaires.sections.index', $item) }}">Continue Editing</a>@else<a class="button button-secondary" href="{{ route('admin.questionnaires.review', $item) }}">Review &amp; Publish</a>@endunless
                                <details class="more">
                                    <summary class="button button-link" aria-label="More actions for {{ $item->title }} v{{ $item->version }}"><i data-lucide="ellipsis"></i></summary>
                                    <div class="more-menu">
                                        <a href="{{ route('admin.questionnaires.preview', $item) }}">Preview</a>
                                        <a href="{{ route('admin.questionnaires.show-details', $item) }}">Details</a>
                                        <a href="{{ route('admin.questionnaires.sections.index', $item) }}">Sections &amp; questions</a>
                                        <a href="{{ route('admin.questionnaires.scoring', $item) }}">Scoring</a>
                                        <a href="{{ route('admin.questionnaires.review', $item) }}">Review &amp; publish</a>
                                        @if($item->status !== 'archived')<form method="POST" action="{{ route('admin.questionnaires.archive', $item) }}">@csrf @method('PATCH')<button type="submit">Archive</button></form>@endif
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
                <span class="questionnaire-visually-hidden">All questionnaires ({{ $versions->count() }})</span>
            </section>
        </div>
        <aside class="questionnaire-helper stack-sm">
            <section class="panel">
                <h2><i data-lucide="zap"></i>Quick Tools</h2>
                <p class="questionnaire-side-subtitle">Additional tools and resources.</p>
                <div class="quick-actions questionnaire-quick-actions">
                    <a class="quick-action" href="{{ route('admin.questions.index') }}"><i data-lucide="book-open"></i><span><strong>Open Question Bank</strong><small>Browse and reuse questions</small></span><i data-lucide="chevron-right"></i></a>
                    @if($versions->firstWhere('is_active', true) ?? $versions->first())
                        @php($previewQuestionnaire = $versions->firstWhere('is_active', true) ?? $versions->first())
                        <a class="quick-action" href="{{ route('admin.questionnaires.preview', $previewQuestionnaire) }}"><i data-lucide="eye"></i><span><strong>Preview Active Questionnaire</strong><small>View the questionnaire</small></span><i data-lucide="chevron-right"></i></a>
                    @else
                        <a class="quick-action" href="{{ route('admin.questionnaires.create') }}"><i data-lucide="eye"></i><span><strong>Preview Active Questionnaire</strong><small>Create one to preview</small></span><i data-lucide="chevron-right"></i></a>
                    @endif
                    <a class="quick-action" href="{{ route('admin.questionnaires.trash') }}"><i data-lucide="trash-2"></i><span><strong>Open Trash</strong><small>Manage deleted items</small></span><i data-lucide="chevron-right"></i></a>
                    <a class="quick-action" href="{{ route('admin.questionnaires.reports') }}"><i data-lucide="bar-chart-3"></i><span><strong>Reports &amp; History</strong><small>Results, analytics and versions</small></span><i data-lucide="chevron-right"></i></a>
                </div>
            </section>
            <section class="panel workflow-card">
                <h2><i data-lucide="circle-help"></i>Need Help?</h2>
                <p class="questionnaire-side-subtitle">Follow the process to create and publish a questionnaire.</p>
                <ol>
                    <li><span>1</span><div><a href="{{ route('admin.questionnaires.create') }}">Create a questionnaire</a><small>Set title, description and basic configuration</small></div></li>
                    <li><span>2</span><div><a href="{{ $versions->first() ? route('admin.questionnaires.sections.index', $versions->first()) : route('admin.questionnaires.create') }}">Add sections and questions</a><small>Organise sections and add questions</small></div></li>
                    <li><span>3</span><div><a href="{{ $versions->first() ? route('admin.questionnaires.scoring', $versions->first()) : route('admin.questionnaires.create') }}">Configure scoring</a><small>Set score ranges and support content</small></div></li>
                    <li><span>4</span><div><a href="{{ $versions->first() ? route('admin.questionnaires.review', $versions->first()) : route('admin.questionnaires.create') }}">Review and publish</a><small>Validate and publish when ready</small></div></li>
                </ol>
                <a class="questionnaire-guide" href="#questionnaire-workflow"><i data-lucide="book-open"></i>View Full Guide<i data-lucide="external-link"></i></a>
            </section>
        </aside>
    </div>
</main>
<style>
    .questionnaire-home{color:#102b43}.questionnaire-home .questionnaire-workspace-grid{grid-template-columns:minmax(0,1fr) 232px;gap:16px}.questionnaire-home .questionnaire-main-column{gap:12px}.questionnaire-home .questionnaire-heading{padding:0 2px 3px}.questionnaire-home .questionnaire-heading h2{font-size:2rem;line-height:1.05;letter-spacing:-.04em;margin:5px 0 2px;color:#071d4a}.questionnaire-home .questionnaire-heading p{font-size:.9rem;color:var(--muted)}
    .questionnaire-hero{position:relative;overflow:hidden;display:flex;min-height:206px;padding:18px 20px 15px;border:1px solid #dae9ff;border-radius:10px;background:radial-gradient(circle at 75% 87%,#d5e9ff 0,#e9f3ff 38%,transparent 64%),linear-gradient(110deg,#e9f3ff,#f1f7ff 60%,#e7f1ff)}.questionnaire-hero-copy{z-index:1;width:58%;min-width:410px}.questionnaire-eyebrow{display:block;margin-bottom:5px;color:#38639b;font-size:.62rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase}.questionnaire-hero h2{margin:0 0 4px;font-size:1.45rem;line-height:1.1;color:#102b43}.questionnaire-hero p{margin:0 0 10px;max-width:410px;color:#526986;font-size:.78rem;line-height:1.38}.questionnaire-create{display:flex;align-items:center;gap:11px;width:288px;height:41px;padding:0 15px;border-radius:7px;background:linear-gradient(90deg,#2f7fc9,#2170ba);box-shadow:0 7px 16px #a8c7e7;color:#fff;text-decoration:none;font-size:.85rem;font-weight:700}.questionnaire-create svg{width:19px}.questionnaire-create svg:last-child{margin-left:auto;width:16px}.questionnaire-create:hover{filter:brightness(1.08)}.questionnaire-hero-links{display:flex;gap:9px;margin-top:9px}.questionnaire-hero-links a{display:flex;justify-content:center;align-items:center;gap:8px;height:30px;padding:0 12px;border:1px solid #e0eaf4;border-radius:7px;background:#fff;color:#15365c;text-decoration:none;font-size:.68rem;font-weight:700;box-shadow:0 4px 12px #d6e5f5}.questionnaire-hero-links svg{width:16px;height:16px;color:#2a619b}
    .questionnaire-hero-art{position:absolute;inset:0 0 0 45%;pointer-events:none}.questionnaire-hero-art svg{position:absolute;bottom:-29px;left:1%;width:45%;height:103%;opacity:.75}.questionnaire-art-paper{position:absolute;top:47px;left:49%;width:122px;height:158px;border:1px solid #cddbeb;border-radius:6px;background:#fff;box-shadow:0 2px 8px #b9cbe0;transform:rotate(6deg)}.paper-front{top:26px;left:42%;transform:rotate(2deg);padding:13px 15px}.paper-back:before{content:"";position:absolute;left:16px;right:16px;top:23px;height:10px;border-radius:5px;background:#edf3f8;box-shadow:0 26px #edf3f8,0 52px #edf3f8}.paper-front span{display:block;width:36px;height:5px;border-radius:5px;background:#dbe8f4}.paper-front strong{display:block;margin:7px 0 12px;color:#183b5c;font-size:.67rem;line-height:1.15}.paper-front i{position:relative;display:block;width:12px;height:12px;margin:14px 0;border:1px solid #b6d2ea;border-radius:50%}.paper-front i:after{content:"";position:absolute;top:3px;left:19px;width:69px;height:5px;border-radius:3px;background:#e5edf5;box-shadow:0 10px #edf3f8}.questionnaire-hero-art em{position:absolute;right:15px;bottom:41px;color:#536f94;font:italic .82rem Georgia,serif;line-height:1.2;transform:rotate(-8deg)}
    .questionnaire-journey{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));min-height:70px;padding:10px 12px;border:1px solid #e2eaf4;border-radius:9px;background:#fff;box-shadow:0 6px 19px #e8edf5}.questionnaire-journey a{display:flex;align-items:center;gap:7px;position:relative;min-width:0;color:#172f4d;text-decoration:none}.questionnaire-journey a:not(:last-child):after{content:"›";position:absolute;right:5px;top:17px;color:#4d76a6;font-size:1.4rem}.questionnaire-journey b{display:grid;place-items:center;align-self:flex-start;width:26px;height:26px;flex:0 0 26px;border-radius:50%;background:#e2efff;color:#1f69b4;font-size:.8rem}.questionnaire-journey svg{width:23px;height:23px;flex:0 0 23px;color:#1c4d83}.questionnaire-journey strong{display:block;font-size:.58rem;line-height:1.2}.questionnaire-journey small{display:block;margin-top:2px;color:#526681;font-size:.57rem;line-height:1.2}
    .questionnaire-home .questionnaire-list-panel{padding:14px 13px 9px;border-radius:10px}.questionnaire-home .questionnaire-list-panel h2{margin:0 0 2px;font-size:1rem}.questionnaire-home .questionnaire-list-panel .lede{font-size:.7rem;color:#60728b}.questionnaire-home .questionnaire-search{width:184px;height:31px;border-radius:7px}.questionnaire-home .questionnaire-search input{font-size:.65rem}.questionnaire-home .questionnaire-filter{min-height:31px!important;border-radius:7px!important;background:#fff;font-size:.65rem!important}.questionnaire-status-tabs{display:flex;gap:11px;margin:11px 0 8px;border-bottom:1px solid #e5eaf2}.questionnaire-status-tabs button{padding:0 14px 8px;border:0;border-bottom:2px solid transparent;background:none;color:#60728b;cursor:pointer;font-size:.67rem}.questionnaire-status-tabs button[aria-selected="true"]{border-color:#1d70c1;color:#155eab;font-weight:700}
    .questionnaire-home .questionnaire-table{display:grid;gap:6px;overflow:visible}.questionnaire-home .questionnaire-table-head{display:none}.questionnaire-home .questionnaire-row{display:grid;grid-template-columns:36px minmax(150px,1.5fr) minmax(70px,.55fr) minmax(93px,.82fr) minmax(140px,auto);gap:9px;align-items:center;min-width:0;min-height:61px;padding:8px 12px;border:1px solid #e3eaf3;border-radius:9px;background:#fff}.questionnaire-home .questionnaire-row[hidden]{display:none}.questionnaire-row-icon{display:grid;place-items:center;width:34px;height:34px;border-radius:50%;color:#2a67b5;background:#e7f1ff}.questionnaire-row-icon.live{color:#078773;background:#e4f5f1}.questionnaire-row-icon svg{width:18px;height:18px}.questionnaire-home .questionnaire-title-cell{gap:3px}.questionnaire-home .questionnaire-title-cell .item-title{font-size:.7rem;line-height:1.15;color:#172f4d}.questionnaire-home .questionnaire-title-cell small,.questionnaire-home .questionnaire-updated small{color:#697b91;font-size:.59rem;line-height:1.2}.questionnaire-home .badge{gap:6px;max-width:100%;padding:5px 8px;border-radius:15px;background:#f0eafb;color:#6147a2;font-size:.6rem;white-space:nowrap}.questionnaire-home .badge.active{background:#e2f5ef;color:#14846b}.questionnaire-home .badge .status-dot{width:6px;height:6px;box-shadow:none}.questionnaire-home .questionnaire-updated{gap:1px}.questionnaire-home .questionnaire-updated strong{font-size:.62rem}.questionnaire-home .questionnaire-row-actions{flex-wrap:nowrap;justify-content:flex-end;gap:9px}.questionnaire-home .questionnaire-row-actions .button{min-height:31px;padding:0 11px;border-radius:7px;background:#1872bd;color:#fff;box-shadow:none;white-space:nowrap;font-size:.6rem}.questionnaire-home .questionnaire-row-actions .button-secondary{background:#e4f4f0;color:#244c49}.questionnaire-home .questionnaire-row-actions .more summary{display:grid;place-items:center;width:31px;height:31px;padding:0;border:1px solid #dfe9f3;border-radius:7px;background:#fff;color:#24598f;cursor:pointer;list-style:none}.questionnaire-home .questionnaire-row-actions .more summary::-webkit-details-marker{display:none}.questionnaire-home .questionnaire-row-actions .more summary svg{width:17px}.questionnaire-visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    .questionnaire-home .questionnaire-helper{top:15px;padding-top:4px}.questionnaire-home .questionnaire-helper .panel{padding:13px 11px;border-radius:10px}.questionnaire-home .questionnaire-helper h2{gap:10px;margin:0 0 5px;font-size:1rem}.questionnaire-home .questionnaire-helper h2 svg{width:18px;height:18px;color:#1768b2}.questionnaire-side-subtitle{margin:0 0 12px;color:#5a708c;font-size:.7rem;line-height:1.35}.questionnaire-home .questionnaire-quick-actions{gap:7px}.questionnaire-home .questionnaire-quick-actions .quick-action{min-height:48px;padding:7px 8px;border-radius:7px;color:#0a655e;background:linear-gradient(90deg,#eaf8f7,#f0f6ff)}.questionnaire-home .questionnaire-quick-actions .quick-action:nth-child(2){color:#6046aa;background:linear-gradient(90deg,#f2ecff,#f7f3ff)}.questionnaire-home .questionnaire-quick-actions .quick-action:nth-child(3){color:#4c44ae;background:linear-gradient(90deg,#eeeaff,#f3f6ff)}.questionnaire-home .questionnaire-quick-actions .quick-action svg{width:17px;height:17px}.questionnaire-home .questionnaire-quick-actions .quick-action svg:last-child{width:14px;height:14px}.questionnaire-home .questionnaire-quick-actions .quick-action span{display:grid;gap:2px}.questionnaire-home .questionnaire-quick-actions strong{font-size:.61rem}.questionnaire-home .questionnaire-quick-actions small{font-size:.59rem;color:#61738b}.questionnaire-home .workflow-card ol{gap:12px;margin:10px 0 17px}.questionnaire-home .workflow-card li{gap:8px}.questionnaire-home .workflow-card li:not(:last-child):after{top:25px;left:12px;bottom:-12px;height:auto}.questionnaire-home .workflow-card li span{width:24px;height:24px;flex:0 0 24px}.questionnaire-home .workflow-card a{font-size:.61rem}.questionnaire-home .workflow-card small{font-size:.61rem}.questionnaire-guide{display:flex;align-items:center;gap:10px;min-height:43px;padding:0 12px;border-radius:7px;background:#eaf3ff;color:#1f4e86;text-decoration:none;font-size:.64rem;font-weight:700}.questionnaire-guide svg{width:18px;height:18px}.questionnaire-guide svg:last-child{width:14px;height:14px;margin-left:auto}
    .questionnaire-home .questionnaire-row:nth-last-child(-n+2) .more-menu{top:auto;bottom:calc(100% + 6px)}
    .questionnaire-home .questionnaire-row:has(.more[open]){z-index:30}
    @media(max-width:1150px){.questionnaire-home .questionnaire-workspace-grid{grid-template-columns:minmax(0,1fr) 215px}.questionnaire-journey{grid-template-columns:repeat(2,1fr);gap:10px}.questionnaire-journey a:after{display:none}.questionnaire-home .questionnaire-row{grid-template-columns:36px minmax(150px,1fr) 80px minmax(140px,auto)}.questionnaire-home .questionnaire-updated{display:none}}
    @media(max-width:900px){.questionnaire-home .questionnaire-workspace-grid{grid-template-columns:1fr}.questionnaire-home .questionnaire-helper{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));padding:0}.questionnaire-hero-copy{min-width:0;width:60%}}
    @media(max-width:720px){.questionnaire-hero-art{opacity:.45}.questionnaire-hero-copy{width:100%}.questionnaire-home .questionnaire-helper{grid-template-columns:1fr}.questionnaire-home .questionnaire-row{grid-template-columns:34px minmax(110px,1fr) auto}.questionnaire-home .questionnaire-row>span:nth-of-type(2),.questionnaire-home .questionnaire-updated{display:none}.questionnaire-home .questionnaire-row-actions{grid-column:2 / -1;justify-content:flex-start}}
    @media(max-width:480px){.questionnaire-hero{padding:17px}.questionnaire-hero h2{font-size:1.22rem}.questionnaire-hero p{max-width:320px}.questionnaire-create{width:100%}.questionnaire-hero-links{flex-wrap:wrap}.questionnaire-journey{grid-template-columns:1fr}.questionnaire-list-tools{width:100%}.questionnaire-home .questionnaire-search{flex:1}.questionnaire-status-tabs{overflow-x:auto;gap:0}.questionnaire-status-tabs button{white-space:nowrap;padding-inline:11px}}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.querySelector('[data-questionnaire-search]');
    const filter = document.querySelector('[data-questionnaire-filter]');
    const rows = Array.from(document.querySelectorAll('[data-questionnaire-row]'));
    const empty = document.querySelector('[data-questionnaire-empty]');
    const tabs = Array.from(document.querySelectorAll('[data-status-tab]'));
    let selectedStatus = 'all';
    function updateRows() {
        const query = (search?.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach(function (row) {
            const visibleRow = (!query || row.dataset.search.includes(query)) && (selectedStatus === 'all' || row.dataset.status === selectedStatus);
            row.hidden = !visibleRow;
            if (visibleRow) visible++;
        });
        if (empty) empty.hidden = visible !== 0 || rows.length === 0;
    }
    function selectStatus(status) {
        selectedStatus = status;
        tabs.forEach(function (tab) { tab.setAttribute('aria-selected', String(tab.dataset.statusTab === status)); });
        filter?.setAttribute('aria-pressed', String(status === 'draft'));
        filter?.classList.toggle('is-selected', status === 'draft');
        updateRows();
    }
    tabs.forEach(function (tab) { tab.addEventListener('click', function () { selectStatus(tab.dataset.statusTab); }); });
    search?.addEventListener('input', updateRows);
    filter?.addEventListener('click', function () { selectStatus(selectedStatus === 'draft' ? 'all' : 'draft'); });
    document.querySelector('[data-show-drafts]')?.addEventListener('click', function () { selectStatus('draft'); });
});
</script>
<style>
    .questionnaire-home .questionnaire-table-head{font-size:.72rem}
    .questionnaire-home .questionnaire-title-cell small,.questionnaire-home .questionnaire-updated small{font-size:.75rem}
    .questionnaire-home .questionnaire-summary-cell{font-size:.8rem}
    .questionnaire-home .questionnaire-updated{font-size:.82rem}
    .questionnaire-home .questionnaire-row-actions .button{font-size:.8rem}
    .questionnaire-home .questionnaire-search input,.questionnaire-home .questionnaire-filter{font-size:.82rem!important}
    .questionnaire-home .workflow-card small{font-size:.75rem}
</style>
@endsection
