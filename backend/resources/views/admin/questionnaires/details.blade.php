@extends('layouts.admin')
@section('title', 'Basic info · '.$questionnaire->title)
@section('body')
@php($justCreated = (int) session('admin_questionnaire_creation_id') === $questionnaire->id && $questionnaire->status === 'draft')
<main class="content stack questionnaire-creation-step">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaire Management</a>

    @if(session('status'))
        @if($justCreated && str_starts_with((string) session('status'), 'Questionnaire created'))<div class="status creation-success"><i data-lucide="circle-check"></i><div><strong>Questionnaire created successfully!</strong><span>You can now complete the remaining steps to add sections, questions, scoring and result levels.</span></div><button type="button" aria-label="Dismiss" onclick="this.parentElement.remove()">×</button></div>
        @else<div class="status">{{ session('status') }}</div>@endif
    @endif
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <div>
        <div class="eyebrow">Step 1 of 5 · Basic info</div>
        <h1 class="details-page-title" style="overflow-wrap:anywhere">{{ $questionnaire->title }}
            <span class="badge {{ $questionnaire->is_active && ! $questionnaire->isScheduled() ? 'active' : '' }}" style="vertical-align:middle;font-family:system-ui,sans-serif">{{ $questionnaire->publishState() }}</span>
        </h1>
        <p class="lede">What the questionnaire is called, what it is for, and when it goes live.</p>
    </div>

    @include('admin.questionnaires._creation_progress', ['questionnaire' => $questionnaire, 'review' => $review, 'step' => 1])

    <div class="creation-details-layout">
    <section class="panel">
        <div class="panel-head"><h2 style="margin:0">Basic information</h2></div>
        <form class="stack-sm" method="POST" action="{{ route('admin.questionnaires.details', $questionnaire) }}">@csrf @method('PATCH')
            {{-- Version, status and activation are managed by Review & publish;
                 they ride along unchanged so this form only touches wording. --}}
            <input type="hidden" name="version" value="{{ $questionnaire->version }}">
            <input type="hidden" name="status" value="{{ $questionnaire->status }}">
            @if($questionnaire->is_active)<input type="hidden" name="is_active" value="1">@endif
            <div><label for="q-title">Title</label><input id="q-title" name="title" value="{{ old('title', $questionnaire->title) }}" required></div>
            <div><label for="q-description">Description / instructions <span class="muted">(optional — shown to students on the intro screen)</span></label><textarea id="q-description" name="description" rows="3">{{ old('description', $questionnaire->description) }}</textarea></div>
            <div class="field-row">
                <div style="max-width:360px"><label for="q-purpose">Questionnaire type</label>
                    @if($canChangePurpose)
                        <select id="q-purpose" name="purpose">
                            <option value="{{ \App\Models\Questionnaire::PURPOSE_LIBRARY }}" @selected(old('purpose', $questionnaire->purpose) === 'library')>Library assessment — students choose to take it</option>
                            <option value="{{ \App\Models\Questionnaire::PURPOSE_REGISTRATION }}" @selected(old('purpose', $questionnaire->purpose) === 'registration')>Registration baseline — the mandatory first check-in</option>
                        </select>
                        @if($hasOtherRegistration && $questionnaire->purpose !== 'registration')<small class="muted">A registration baseline already exists; choosing it here makes this a new version of it.</small>@endif
                    @else
                        <input type="hidden" name="purpose" value="{{ $questionnaire->purpose }}">
                        <input value="{{ $questionnaire->isRegistration() ? 'Registration baseline' : 'Library assessment' }}" disabled>
                        <small class="muted">Fixed once a questionnaire is published or has results.</small>
                    @endif
                </div>
                <div style="max-width:220px"><label for="q-minutes">Estimated time <span class="muted">(minutes)</span></label><input id="q-minutes" name="estimated_minutes" type="number" min="1" max="600" value="{{ old('estimated_minutes', $questionnaire->estimated_minutes) }}" placeholder="e.g. 10"></div>
            </div>
            <div><label for="published_at">Go live at <span class="muted">(optional — leave blank to go live as soon as you publish)</span></label>
            <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', optional($questionnaire->published_at)->format('Y-m-d\TH:i')) }}" style="max-width:280px"></div>
            @if($questionnaire->status === 'draft')<div class="creation-draft-notice"><i data-lucide="info"></i><div><strong>This questionnaire is currently a draft.</strong><span>Nothing will be visible to students until you complete the Review &amp; Publish step.</span></div></div>@endif
            <div class="actions creation-details-actions"><button class="button button-secondary" type="submit">Save</button><button class="button" type="submit" name="next" value="sections">Save &amp; continue to Sections &amp; Questions <i data-lucide="arrow-right"></i></button></div>
        </form>
    </section>
    <aside class="panel creation-details-summary" aria-label="Questionnaire summary">
        <h2><i data-lucide="layers"></i>Status &amp; version</h2>
        <div><span>Version</span><strong>v{{ $questionnaire->version }}</strong></div>
        <div><span>Status</span><strong class="badge">{{ $questionnaire->publishState() }}</strong></div>
        <div><span>Type</span><strong>{{ $questionnaire->isRegistration() ? 'Registration baseline' : 'Library assessment' }}</strong></div>
        <h2><i data-lucide="chart-no-axes-combined"></i>Scoring</h2>
        <div><span>Method</span><strong>{{ $questionnaire->scoringMethodLabel() }}</strong></div>
        <div><span>Section weights</span><strong>{{ $questionnaire->usesEqualSectionWeights() ? 'Equal' : 'Custom' }}</strong></div>
        <div><span>Result scale</span><strong>{{ $resultScale ? $resultScale[0].'–'.$resultScale[1] : '—' }}{{ $resultScale === [0, 100] ? ' (%)' : '' }}</strong></div>
        <div><span>Questions</span><strong>{{ $questionCount }}</strong></div>
        <p class="muted" style="font-size:.8rem;margin:10px 0 0">Change these on the <a href="{{ route('admin.questionnaires.scoring', $questionnaire) }}">Scoring</a> step.</p>
        <h2><i data-lucide="eye"></i>Actions</h2>
        <div class="actions" style="flex-wrap:wrap">
            <a class="button button-secondary" href="{{ route('admin.questionnaires.preview', $questionnaire) }}">Preview</a>
            <form method="POST" action="{{ route('admin.questionnaires.duplicate', $questionnaire) }}">@csrf<button class="button button-secondary" type="submit">Duplicate</button></form>
        </div>
    </aside>
    </div>
</main>

<style>
.questionnaire-creation-step{max-width:1260px}.creation-success{display:flex;align-items:center;gap:14px;padding:16px 18px;background:#e8f8f0;border-color:#b8e5d1;color:#235c4c}.creation-success svg{width:28px;height:28px;color:#12945f;flex:0 0 28px}.creation-success div{display:grid;gap:3px;flex:1}.creation-success strong{color:#087348;font-size:.95rem}.creation-success span{font-size:.82rem}.creation-success button{border:0;background:transparent;color:#226253;font-size:1.4rem;cursor:pointer}.details-page-title{margin:4px 0 4px;color:#102b43;font-size:2rem;letter-spacing:-.035em}.creation-details-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,.72fr);gap:20px;align-items:start}.creation-details-layout>.panel{padding:22px;border-radius:14px}.creation-details-layout .panel-head{margin-bottom:16px}.creation-details-layout .panel-head h2,.creation-details-summary h2{font-size:1.15rem;color:#102b43}.creation-details-summary{display:grid;gap:10px}.creation-details-summary h2{display:flex;align-items:center;gap:9px;margin:0 0 2px}.creation-details-summary h2:not(:first-child){margin-top:14px}.creation-details-summary h2 svg{width:20px;height:20px;color:#2877be}.creation-details-summary>div{display:flex;justify-content:space-between;align-items:center;color:#526b75;font-size:.88rem}.creation-details-summary strong{color:#203f61}.creation-draft-notice{display:flex;align-items:flex-start;gap:11px;margin-top:4px;padding:14px;border-radius:10px;background:#eaf4ff;color:#285783}.creation-draft-notice svg{width:22px;height:22px;color:#2877be;flex:0 0 22px}.creation-draft-notice div{display:grid;gap:3px}.creation-draft-notice strong{color:#245177;font-size:.88rem}.creation-draft-notice span{font-size:.8rem}.creation-details-actions{justify-content:space-between;margin-top:8px}.creation-details-actions .button:last-child{margin-left:auto}@media(max-width:900px){.creation-details-layout{grid-template-columns:1fr}}@media(max-width:600px){.creation-success{align-items:flex-start}.details-page-title{font-size:1.7rem}.creation-details-actions{flex-direction:column}.creation-details-actions .button{width:100%}.creation-details-actions .button:last-child{margin-left:0}}
</style>
@endsection
