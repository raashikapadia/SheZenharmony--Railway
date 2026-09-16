@extends('layouts.admin')
@section('title', 'Details · '.$questionnaire->title)
@section('body')
@php($creationFlow = (int) session('admin_questionnaire_creation_id') === $questionnaire->id && $questionnaire->status === 'draft')
<main class="content stack{{ $creationFlow ? ' questionnaire-creation-step' : '' }}">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaire Management</a>

    @if(session('status'))
        @if($creationFlow)<div class="status creation-success"><i data-lucide="circle-check"></i><div><strong>Questionnaire created successfully!</strong><span>You can now complete the remaining steps to add sections, questions and scoring.</span></div><button type="button" aria-label="Dismiss" onclick="this.parentElement.remove()">×</button></div>
        @else<div class="status">{{ session('status') }}</div>@endif
    @endif
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <div>
        <div class="eyebrow">{{ $creationFlow ? 'Step 1 of 4 · New questionnaire setup' : 'Step 1 of 3' }}</div>
        <h1 class="details-page-title" style="overflow-wrap:anywhere">{{ $creationFlow ? 'Questionnaire Details' : 'Details' }}
            <span class="badge {{ $questionnaire->is_active && ! $questionnaire->isScheduled() ? 'active' : '' }}" style="vertical-align:middle;font-family:system-ui,sans-serif">{{ $questionnaire->publishState() }}</span>
        </h1>
        <p class="lede">{{ $creationFlow ? 'Confirm or update the basic details of your new questionnaire before adding sections and questions.' : 'What the questionnaire is called, the scale results are reported on, and what each result means.' }}</p>
    </div>

    @if($creationFlow)
        @include('admin.questionnaires._creation_progress', ['questionnaire' => $questionnaire, 'step' => 1])
    @else
        @include('admin.questionnaires._wizard', ['questionnaire' => $questionnaire, 'review' => $review, 'step' => 1])
    @endif

    {{-- ============ NAME & DESCRIPTION ============ --}}
    @if($creationFlow)<div class="creation-details-layout">@endif
    <section class="panel">
        <div class="panel-head"><h2 style="margin:0">{{ $creationFlow ? 'Basic Information' : 'Questionnaire' }}</h2></div>
        <form class="stack-sm" method="POST" action="{{ route('admin.questionnaires.details', $questionnaire) }}">@csrf @method('PATCH')
            {{-- Version, status and activation are managed by Review & publish;
                 they ride along unchanged so this form only touches wording. --}}
            <input type="hidden" name="version" value="{{ $questionnaire->version }}">
            <input type="hidden" name="status" value="{{ $questionnaire->status }}">
            @if($questionnaire->is_active)<input type="hidden" name="is_active" value="1">@endif
            <div><label for="q-title">Title</label><input id="q-title" name="title" value="{{ old('title', $questionnaire->title) }}" required></div>
            <div><label for="q-description">Description <span class="muted">(optional — shown to students on the intro screen)</span></label><textarea id="q-description" name="description" rows="2">{{ old('description', $questionnaire->description) }}</textarea></div>
            <div><label for="published_at">Go live at <span class="muted">(optional — leave blank to go live as soon as you publish)</span></label>
            <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', optional($questionnaire->published_at)->format('Y-m-d\TH:i')) }}" style="max-width:280px"></div>
            @if($creationFlow)<div class="creation-draft-notice"><i data-lucide="info"></i><div><strong>This questionnaire is currently a draft.</strong><span>Nothing will be visible to students until you complete the Review &amp; Publish step.</span></div></div>@endif
            <div class="actions creation-details-actions"><button class="button{{ $creationFlow ? ' button-secondary' : '' }}" type="submit">{{ $creationFlow ? 'Save Changes' : 'Save' }}</button>@if($creationFlow)<button class="button" type="submit" name="next" value="sections">Continue to Sections &amp; Questions <i data-lucide="arrow-right"></i></button>@endif</div>
        </form>
    </section>
    @if($creationFlow)
        <aside class="panel creation-details-summary" aria-label="New questionnaire summary">
            <h2><i data-lucide="layers"></i>Status &amp; Version</h2>
            <div><span>Version</span><strong>v{{ $questionnaire->version }}</strong></div>
            <div><span>Status</span><strong class="badge">{{ $questionnaire->publishState() }}</strong></div>
            <h2><i data-lucide="chart-no-axes-combined"></i>Result Scale</h2>
            <div><span>Minimum</span><strong>{{ $resultScale[0] ?? '—' }}</strong></div>
            <div><span>Maximum</span><strong>{{ $resultScale[1] ?? '—' }}</strong></div>
        </aside>
    </div>
    @endif

    @unless($creationFlow)
        @include('admin.questionnaires._ranges_editor')
    @endunless

    @unless($creationFlow)<div class="split" style="align-items:center">
        <span class="muted">Next: build the questionnaire.</span>
        <a class="button" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">Sections &amp; questions →</a>
    </div>@endunless
</main>

@if($creationFlow)<style>
.questionnaire-creation-step{max-width:1260px}.creation-success{display:flex;align-items:center;gap:14px;padding:16px 18px;background:#e8f8f0;border-color:#b8e5d1;color:#235c4c}.creation-success svg{width:28px;height:28px;color:#12945f;flex:0 0 28px}.creation-success div{display:grid;gap:3px;flex:1}.creation-success strong{color:#087348;font-size:.95rem}.creation-success span{font-size:.82rem}.creation-success button{border:0;background:transparent;color:#226253;font-size:1.4rem;cursor:pointer}.details-page-title{margin:4px 0 4px;color:#102b43;font-size:2rem;letter-spacing:-.035em}.creation-details-layout{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,.72fr);gap:20px;align-items:start}.creation-details-layout>.panel{padding:22px;border-radius:14px}.creation-details-layout .panel-head{margin-bottom:16px}.creation-details-layout .panel-head h2,.creation-details-summary h2{font-size:1.15rem;color:#102b43}.creation-details-summary{display:grid;gap:10px}.creation-details-summary h2{display:flex;align-items:center;gap:9px;margin:0 0 2px}.creation-details-summary h2:not(:first-child){margin-top:14px}.creation-details-summary h2 svg{width:20px;height:20px;color:#2877be}.creation-details-summary>div{display:flex;justify-content:space-between;align-items:center;color:#526b75;font-size:.88rem}.creation-details-summary strong{color:#203f61}.creation-draft-notice{display:flex;align-items:flex-start;gap:11px;margin-top:4px;padding:14px;border-radius:10px;background:#eaf4ff;color:#285783}.creation-draft-notice svg{width:22px;height:22px;color:#2877be;flex:0 0 22px}.creation-draft-notice div{display:grid;gap:3px}.creation-draft-notice strong{color:#245177;font-size:.88rem}.creation-draft-notice span{font-size:.8rem}.creation-details-actions{justify-content:space-between;margin-top:8px}.creation-details-actions .button:last-child{margin-left:auto}@media(max-width:900px){.creation-details-layout{grid-template-columns:1fr}}@media(max-width:600px){.creation-success{align-items:flex-start}.details-page-title{font-size:1.7rem}.creation-details-actions{flex-direction:column}.creation-details-actions .button{width:100%}.creation-details-actions .button:last-child{margin-left:0}}
</style>@endif


@endsection
