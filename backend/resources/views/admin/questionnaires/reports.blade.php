@extends('layouts.admin')
@section('title', 'Reports & History · Questionnaire Management')
@section('body')
<main class="content admin-generic-page">
    @include('admin.questionnaires._tabs')
    <div class="page-intro reports-heading">
        <div>
            <h2>Reports &amp; History</h2>
            <p class="lede">A read-only overview of questionnaire activity, publication status, and assessment reporting.</p>
        </div>
    </div>
    <section class="cards questionnaire-report-metrics">
        <article class="card"><span class="report-kicker">Live questionnaires</span><strong>{{ $activeCount }}</strong><span class="muted">Currently available to students</span></article>
        <article class="card"><span class="report-kicker">Drafts to review</span><strong>{{ $draftCount }}</strong><span class="muted">Versions still being prepared</span></article>
        <article class="card"><span class="report-kicker">Completed assessments</span><strong>{{ $assessmentCount }}</strong><span class="muted">Available in Results</span></article>
        <article class="card"><span class="report-kicker">Archived versions</span><strong>{{ $archivedCount }}</strong><span class="muted">Kept for historical reference</span></article>
    </section>
    <div class="reports-dashboard-grid">
        <section class="panel reports-attention">
            <div class="report-section-heading"><div><h3>What needs attention</h3><p class="muted">Draft questionnaires that may need editing or publication.</p></div><a class="button button-secondary" href="{{ route('admin.questionnaires.index') }}">Manage questionnaires</a></div>
            @forelse($attention as $item)
                <a class="report-list-row" href="{{ route('admin.questionnaires.sections.index', $item) }}"><span><strong>{{ $item->title }}</strong><small>v{{ $item->version }} · {{ $item->sections_count }} sections · {{ $item->questions_count }} questions</small></span><span class="report-row-action">Edit <span aria-hidden="true">→</span></span></a>
            @empty
                <p class="muted report-empty">There are no draft questionnaires waiting for attention.</p>
            @endforelse
        </section>
        <section class="panel reports-actions">
            <h3>Explore reports</h3><p class="muted">Open a focused view when you need more detail.</p>
            <a class="report-action-card" href="{{ route('admin.questionnaires.results') }}"><span class="report-action-icon">▤</span><span><strong>Results</strong><small>Review completed assessment results.</small></span><span>→</span></a>
            <a class="report-action-card" href="{{ route('admin.questionnaires.analytics') }}"><span class="report-action-icon">▥</span><span><strong>Analytics</strong><small>Explore performance and trends.</small></span><span>→</span></a>
            <a class="report-action-card" href="{{ route('admin.questionnaires.versions') }}"><span class="report-action-icon">◷</span><span><strong>Versions</strong><small>Compare drafts and history.</small></span><span>→</span></a>
        </section>
    </div>
    <section class="panel reports-recent">
        <div class="report-section-heading"><div><h3>Recent questionnaire activity</h3><p class="muted">Newest versions and their current state.</p></div><a class="button button-secondary" href="{{ route('admin.questionnaires.versions') }}">View all versions</a></div>
        <div class="report-recent-list">
            @forelse($recentVersions as $item)
                <div class="report-recent-row"><span class="status-dot {{ $item->is_active ? 'dot-live' : ($item->status === 'draft' ? 'dot-draft' : 'dot-archived') }}"></span><span class="report-recent-title"><strong>{{ $item->title }}</strong><small>Version {{ $item->version }} · {{ $item->sections_count }} sections · {{ $item->questions_count }} questions</small></span><span class="badge {{ $item->is_active ? 'active' : '' }}">{{ $item->is_active ? 'Live' : ucfirst($item->status) }}</span><time>{{ $item->updated_at?->diffForHumans() }}</time></div>
            @empty
                <p class="muted report-empty">No questionnaire activity yet.</p>
            @endforelse
        </div>
    </section>
</main>
<style>
    .reports-heading{margin-bottom:16px}.questionnaire-report-metrics{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:16px}.questionnaire-report-metrics .card{display:flex;flex-direction:column;gap:7px}.report-kicker{color:#60728b;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em}.questionnaire-report-metrics strong{color:#173d63;font:700 1.8rem Georgia,"Times New Roman",serif}.questionnaire-report-metrics .muted{font-size:.72rem}.reports-dashboard-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.65fr);gap:16px}.reports-dashboard-grid .panel{padding:18px}.report-section-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:12px}.report-section-heading h3,.reports-actions h3{margin:0 0 3px;color:#173d63;font-size:1.08rem}.report-section-heading .muted,.reports-actions>.muted{margin:0;font-size:.75rem}.report-section-heading .button{min-height:34px;padding:.5rem .8rem;font-size:.7rem;white-space:nowrap}.report-list-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-top:1px solid #e8eef5;color:#173d63;text-decoration:none}.report-list-row strong,.report-list-row small{display:block}.report-list-row strong{font-size:.78rem}.report-list-row small{margin-top:3px;color:#71829b;font-size:.68rem}.report-row-action{color:#2877be;font-size:.72rem;font-weight:700;white-space:nowrap}.report-empty{padding:14px 0;margin:0;font-size:.78rem}.report-action-card{display:flex;align-items:center;gap:10px;margin-top:9px;padding:11px;border-radius:9px;background:#eef5ff;color:#173d63;text-decoration:none}.report-action-icon{display:grid;place-items:center;width:28px;height:28px;border-radius:50%;color:#2877be;background:#dceaff;font-size:1rem}.report-action-card span:nth-child(2){display:grid;gap:2px;flex:1}.report-action-card strong{font-size:.76rem}.report-action-card small{color:#60728b;font-size:.66rem}.report-action-card>span:last-child{color:#2877be}.reports-recent{margin-top:16px;padding:18px}.report-recent-list{border-top:1px solid #e8eef5}.report-recent-row{display:flex;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid #e8eef5}.report-recent-title{display:grid;gap:3px;flex:1;min-width:0}.report-recent-title strong{color:#173d63;font-size:.76rem}.report-recent-title small{color:#71829b;font-size:.67rem}.report-recent-row time{color:#71829b;font-size:.67rem;white-space:nowrap}.report-recent-row .badge{font-size:.67rem}.report-recent-row .status-dot{flex:0 0 auto}
    @media(max-width:1050px){.questionnaire-report-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.reports-dashboard-grid{grid-template-columns:1fr}}
    @media(max-width:600px){.questionnaire-report-metrics{grid-template-columns:1fr}.report-section-heading{flex-direction:column}.report-recent-row{align-items:flex-start;flex-wrap:wrap}.report-recent-row time{margin-left:18px}}
</style>
@endsection
