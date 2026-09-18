@extends('layouts.admin')
@section('title', 'Student Profile')
@section('body')
<style>
    .student-profile{--student-blue:#2877be;--student-navy:#102d58;--student-line:#e2eaf3;max-width:1500px}
    .student-profile .student-back{display:inline-flex;align-items:center;gap:5px;margin:0 0 14px;color:#526c8e;font-size:.8rem;font-weight:650;text-decoration:none}
    .student-profile .student-back:hover{color:var(--student-blue)}
    .student-identity,.student-tabs,.student-card{border:1px solid var(--student-line);border-radius:13px;background:rgba(255,255,255,.94);box-shadow:0 8px 22px rgba(49,76,117,.07)}
    .student-identity{display:flex;align-items:center;gap:20px;padding:17px 22px;margin-bottom:14px}
    .student-initials{display:grid;flex:0 0 82px;width:82px;height:82px;place-items:center;border-radius:50%;color:var(--student-navy);background:#dceafe;font-size:1.45rem;font-weight:800}
    .student-identity-main{min-width:0;flex:1}.student-identity-main h1{margin:0 0 4px;color:var(--student-navy);font-family:"Segoe UI",Inter,system-ui,sans-serif;font-size:1.35rem;letter-spacing:.01em;overflow-wrap:anywhere}.student-identity-main p{margin:0;color:#667b98;font-size:.76rem}
    .student-status{display:inline-flex;align-items:center;gap:7px;margin-top:8px;padding:5px 11px;border-radius:999px;color:#138566;background:#dcf7ec;font-size:.74rem;font-weight:750}.student-status::before{content:"";width:8px;height:8px;border-radius:50%;background:#16a878}
    .student-status.hold{color:#a52f3c;background:#fff0f1}.student-status.hold::before{background:#d4515d}
    .student-identity-meta{display:grid;grid-template-columns:repeat(3,minmax(105px,1fr));min-width:390px}.student-meta-item{display:flex;align-items:center;gap:9px;padding:4px 18px;border-left:1px solid var(--student-line);color:#607592;font-size:.7rem}.student-meta-item strong{display:block;margin-top:2px;color:var(--student-navy);font-size:.78rem}.student-icon{display:grid;flex:0 0 34px;width:34px;height:34px;place-items:center;border-radius:50%;color:#2368ae;background:#edf5ff}.student-icon svg{width:18px;height:18px;stroke-width:1.8}
    .student-tabs{display:grid;grid-template-columns:repeat(3,1fr);overflow:hidden;margin-bottom:14px}.student-tabs a{display:flex;align-items:center;justify-content:center;gap:10px;min-height:45px;border-bottom:3px solid transparent;color:#4c6280;font-size:.76rem;font-weight:700;text-decoration:none}.student-tabs a:hover{background:#f6faff;color:var(--student-blue)}.student-tabs a.active{border-bottom-color:#2697ee;color:var(--student-blue);background:#fbfdff}.student-tabs svg{width:20px;height:20px;stroke-width:1.8}
    .student-overview-grid{display:grid;grid-template-columns:1.05fr 1.15fr;gap:14px;margin-bottom:14px}.student-right-stack{display:grid;gap:14px}.student-card{padding:18px}.student-card-title{display:flex;align-items:center;gap:9px;margin:0 0 14px;color:var(--student-navy);font-size:1rem}.student-card-title .student-icon{width:35px;height:35px;flex-basis:35px}.student-card-title a{margin-left:auto;color:#1979d1;font-size:.72rem;font-weight:650;text-decoration:none}.student-info-grid{display:grid;grid-template-columns:repeat(3,1fr)}.student-info-item{min-height:61px;padding:9px 14px;border-top:1px solid var(--student-line)}.student-info-item:nth-child(3n+1){padding-left:0}.student-info-item label,.student-stat label{display:block;margin:0 0 5px;color:#70829b;font-size:.68rem;font-weight:500}.student-info-item strong,.student-stat strong{color:var(--student-navy);font-size:.79rem;font-weight:700}.student-info-item .student-status{margin:0;font-size:.68rem}.student-stat-grid{display:grid;grid-template-columns:repeat(4,1fr)}.student-stat{padding:3px 14px;border-left:1px solid var(--student-line)}.student-stat:first-child{padding-left:0;border-left:0}.student-stat strong{display:block}.student-stat .student-status{margin:0;font-size:.64rem;padding:4px 8px}.student-stat .moderate{display:inline-block;padding:5px 9px;border-radius:999px;color:#725914;background:#fff4c9;font-size:.68rem;font-weight:750}.student-engagement{display:grid;grid-template-columns:1fr 1fr}.engagement-stat{display:flex;align-items:center;gap:12px;padding:4px 14px;border-left:1px solid var(--student-line)}.engagement-stat:first-child{padding-left:0;border-left:0}.engagement-stat strong{display:block;color:var(--student-navy);font-size:1.2rem}.engagement-stat small{color:#788aa4;font-size:.66rem}.student-action-card{display:grid;grid-template-columns:.92fr 1.08fr;gap:22px;border-color:#f2c5cb;background:#fff9fa}.student-action-copy{padding-left:0}.student-action-copy h2{margin:0 0 8px;color:var(--student-navy);font-size:1rem}.student-action-copy h3{margin:0 0 5px;color:#314d73;font-family:"Segoe UI",Inter,system-ui,sans-serif;font-size:.75rem}.student-action-copy p{margin:0;color:#6c7d96;font-size:.73rem;line-height:1.45}.student-action-form{padding-left:22px;border-left:1px solid #f0d7db}.student-action-form textarea{min-height:57px;padding:9px;font-size:.74rem}.student-action-form .button-danger{margin-top:7px;background:#d63b43}.student-action-form .button-danger:hover{background:#bc3038}.student-history{margin-top:14px}.student-history h2{margin-bottom:5px;font-size:1.1rem}
    @media(max-width:950px){.student-identity{align-items:flex-start;flex-wrap:wrap}.student-identity-meta{width:100%;min-width:0}.student-meta-item:first-child{padding-left:0;border-left:0}.student-overview-grid{grid-template-columns:1fr}.student-right-stack{grid-template-columns:1fr 1fr}}
    @media(max-width:650px){.student-identity{gap:13px;padding:15px}.student-initials{flex-basis:62px;width:62px;height:62px}.student-identity-main h1{font-size:1.05rem}.student-identity-meta{grid-template-columns:1fr}.student-meta-item{padding:8px 0;border-left:0;border-top:1px solid var(--student-line)}.student-tabs a{font-size:.68rem;gap:5px}.student-tabs svg{width:17px}.student-info-grid,.student-stat-grid,.student-engagement{grid-template-columns:1fr 1fr}.student-info-item:nth-child(3n+1){padding-left:14px}.student-info-item:nth-child(odd){padding-left:0}.student-stat{min-height:55px;margin-bottom:10px;border-left:0}.student-stat:nth-child(even){padding-right:0}.student-right-stack{grid-template-columns:1fr}.student-action-card{grid-template-columns:1fr;gap:15px}.student-action-form{padding:15px 0 0;border-top:1px solid #f0d7db;border-left:0}}
</style>
<main class="content student-profile">
    <a class="student-back" href="{{ route('admin.students.index') }}">← Registered Students</a>
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif

    <section class="student-identity">
        <div class="student-initials">SZ</div>
        <div class="student-identity-main">
            <h1>{{ $student->displayId() }}</h1>
            <p>Pseudonymous student profile. Authentication name and email are not shown here.</p>
            <span class="student-status {{ $student->user?->account_status === 'suspended' ? 'hold' : '' }}">{{ $student->user?->account_status === 'suspended' ? 'On hold' : ($student->user?->account_status ?? 'Unavailable') }}</span>
        </div>
        <div class="student-identity-meta">
            <div class="student-meta-item"><span class="student-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 9h18"/></svg></span><span>Registered<strong>{{ $student->created_at->format('d M Y') }}</strong></span></div>
            <div class="student-meta-item"><span class="student-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg></span><span>Country<strong>{{ $student->profile?->country ?? '—' }}</strong></span></div>
            <div class="student-meta-item"><span class="student-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="3"/><path d="M5 20a7 7 0 0 1 14 0"/></svg></span><span>Age<strong>{{ $student->profile?->age ?? '—' }}</strong></span></div>
        </div>
    </section>

    <nav class="student-tabs" aria-label="Student details tabs">
        <a class="{{ $tab === 'overview' ? 'active' : '' }}" href="{{ route('admin.students.show', $student) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="3"/><path d="M5 20a7 7 0 0 1 14 0"/></svg>Overview</a>
        <a class="{{ $tab === 'assessments' ? 'active' : '' }}" href="{{ route('admin.students.show', [$student, 'tab' => 'assessments']) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3h9l3 3v15H6z"/><path d="M9 12h6M9 16h6M9 8h3"/></svg>Assessments</a>
        <a class="{{ $tab === 'activities' ? 'active' : '' }}" href="{{ route('admin.students.show', [$student, 'tab' => 'activities']) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 8c0 5-8 11-8 11S4 13 4 8a4 4 0 0 1 8-1 4 4 0 0 1 8 1Z"/></svg>Wellbeing Activity</a>
    </nav>

    @if($tab === 'overview')
        <div class="student-overview-grid">
            <section class="student-card">
                <h2 class="student-card-title"><span class="student-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="8" r="3"/><path d="M5 20a7 7 0 0 1 14 0"/></svg></span>Student Information</h2>
                <div class="student-info-grid">
                    <div class="student-info-item"><label>Status</label><span class="student-status {{ $student->user?->account_status === 'suspended' ? 'hold' : '' }}">{{ $student->user?->account_status === 'suspended' ? 'On hold' : ($student->user?->account_status ?? 'Unavailable') }}</span></div>
                    <div class="student-info-item"><label>Date of Birth</label><strong>{{ $student->profile?->date_of_birth?->format('d M Y') ?? '—' }}</strong></div>
                    <div class="student-info-item"><label>Age</label><strong>{{ $student->profile?->age ?? '—' }}</strong></div>
                    <div class="student-info-item"><label>Country</label><strong>{{ $student->profile?->country ?? '—' }}</strong></div>
                    <div class="student-info-item"><label>Year of Study</label><strong>{{ $student->profile?->yearOfStudyDescription() ?? '—' }}</strong></div>
                    <div class="student-info-item"><label>Working</label><strong>{{ $student->profile?->employment_status ?? '—' }}</strong></div>
                    <div class="student-info-item"><label>Relationship Status</label><strong>{{ $student->profile?->relationship_status ?? '—' }}</strong></div>
                    <div class="student-info-item"><label>Children</label><strong>{{ $student->profile?->has_children === null ? '—' : ($student->profile->has_children ? 'Yes' : 'No') }}</strong></div>
                    <div class="student-info-item"><label>Living Arrangement</label><strong>{{ $student->profile?->living_situation ?? '—' }}</strong></div>
                    <div class="student-info-item"><label>Registered</label><strong>{{ $student->created_at->format('d M Y') }}</strong></div>
                </div>
            </section>
            <div class="student-right-stack">
                <section class="student-card">
                    <h2 class="student-card-title"><span class="student-icon" style="color:#7554d6;background:#f0edff"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 3h9l3 3v15H6z"/><path d="M9 12h6M9 16h6M9 8h3"/></svg></span>Assessment Summary<a href="{{ route('admin.students.show', [$student, 'tab' => 'assessments']) }}">View all assessments →</a></h2>
                    <div class="student-stat-grid">
                        <div class="student-stat"><label>Baseline Assessment</label><span class="student-status">{{ $assessments->isNotEmpty() ? 'Completed' : 'Required' }}</span></div>
                        <div class="student-stat"><label>Latest Stress Level</label><span class="moderate">{{ $student->latestAssessment?->stressBand?->label ?? $student->latestAssessment?->wellbeingBand?->label ?? '—' }}</span></div>
                        <div class="student-stat"><label>Total Assessments</label><strong>{{ $assessments->count() }}</strong></div>
                        <div class="student-stat"><label>Last Assessment Date</label><strong>{{ $student->latestAssessment?->completed_at?->format('d M Y') ?? '—' }}</strong></div>
                    </div>
                </section>
                <section class="student-card">
                    <h2 class="student-card-title"><span class="student-icon" style="color:#0d9b74;background:#e5f8f0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 8c0 5-8 11-8 11S4 13 4 8a4 4 0 0 1 8-1 4 4 0 0 1 8 1Z"/></svg></span>Engagement Summary<a href="{{ route('admin.students.show', [$student, 'tab' => 'activities']) }}">View wellbeing activity →</a></h2>
                    <div class="student-engagement">
                        <div class="engagement-stat"><span class="student-icon" style="color:#0d9b74;background:#e5f8f0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 19v-5M12 19V5M19 19v-9"/></svg></span><span><label>Wellbeing Activities Used</label><strong>{{ $usages->count() }}</strong><small>Total activities accessed</small></span></div>
                        <div class="engagement-stat"><span class="student-icon" style="color:#0d9b74;background:#e5f8f0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/></svg></span><span><label>Most Used Category</label><strong>{{ $usages->pluck('intervention.content_type')->filter()->countBy()->sortDesc()->keys()->first() ?? '—' }}</strong><small>Based on activity usage</small></span></div>
                    </div>
                </section>
            </div>
        </div>
    @elseif($tab === 'assessments')
        <section class="student-card student-history"><h2>Assessment history</h2><p class="muted">Completed assessment summaries for this pseudonymous student. Individual answers are not shown here.</p><div class="table-wrap"><table><thead><tr><th>Date</th><th>Questionnaire</th><th>Score</th><th>Stress level</th><th></th></tr></thead><tbody>@forelse($assessments as $assessment)<tr><td>{{ $assessment->completed_at?->format('d M Y') ?? '—' }}</td><td>{{ $assessment->questionnaire?->title ?? 'Assessment' }}</td><td>{{ $assessment->total_score ?? '—' }}</td><td><span class="badge">{{ $assessment->stressBand?->label ?? $assessment->wellbeingBand?->label ?? '—' }}</span></td><td class="actions"><a class="button-link" href="{{ route('admin.student-stress.assessment', $assessment) }}">View details</a></td></tr>@empty<tr><td colspan="5">No completed assessments yet.</td></tr>@endforelse</tbody></table></div></section>
    @elseif($tab === 'activities')
        <section class="student-card student-history"><h2>Wellbeing activity history</h2><p class="muted">Recorded wellbeing activity interactions for this pseudonymous student.</p>@if($usages->isEmpty())<div class="empty-state"><h3>No wellbeing activity recorded yet</h3><p class="muted">Activity history will appear here once student wellbeing interactions are recorded.</p></div>@else<div class="table-wrap"><table><thead><tr><th>Activity</th><th>Category</th><th>Date accessed</th><th>Stress tier</th></tr></thead><tbody>@foreach($usages as $usage)<tr><td>{{ $usage->intervention?->title ?? 'Activity' }}</td><td>{{ $usage->intervention?->content_type ?? '—' }}</td><td>{{ $usage->started_at?->format('d M Y') ?? '—' }}</td><td>{{ $usage->assessment?->stressBand?->label ?? '—' }}</td></tr>@endforeach</tbody></table></div>@endif</section>
    @endif

    <section class="student-card student-action-card" style="margin-top:14px">
        @if($student->user?->account_status === 'suspended')
            <div class="student-action-copy"><h2>Account on hold</h2><p>The student cannot use protected app features until the account is reactivated.</p><div class="student-info-grid" style="margin-top:14px"><div class="student-info-item"><label>Reason shown to student</label><strong>{{ $student->user->account_hold_reason }}</strong></div><div class="student-info-item"><label>Placed on hold</label><strong>{{ $student->user->account_held_at?->format('d M Y, H:i') ?? '—' }}</strong></div></div></div>
            <div class="student-action-form"><form method="POST" action="{{ route('admin.students.reactivate', $student) }}" onsubmit="return confirm('Reactivate this student account?')">@csrf @method('PATCH')<button class="button" type="submit">Reactivate account</button></form></div>
        @elseif($student->user?->account_status === 'active')
            <div class="student-action-copy"><h2>Account Actions</h2><h3>Place account on hold</h3><p>Use this only for a rules or safety violation. The reason is shown to the student, access is blocked, and an email notice is sent.</p></div>
            <div class="student-action-form">@if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif<form method="POST" action="{{ route('admin.students.hold', $student) }}" onsubmit="return confirm('Place this student account on hold?')">@csrf @method('PATCH')<label for="reason">Reason for hold</label><textarea id="reason" name="reason" required minlength="10" maxlength="1000" placeholder="State the rule or safety concern clearly and respectfully.">{{ old('reason') }}</textarea><button class="button button-danger" type="submit">⊘ &nbsp; Place account on hold</button></form></div>
        @else
            <div class="student-action-copy"><h2>Account actions</h2><p>This account is not active, so no moderation action is currently available.</p></div>
        @endif
    </section>
</main>
@endsection
