@extends('layouts.admin')
@section('title', $questionnaire->exists ? 'Edit questionnaire' : 'New questionnaire')
@section('body')
<main class="content">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaires</a>
    <h1>{{ $questionnaire->exists ? 'Edit questionnaire' : 'New questionnaire' }}</h1>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <div class="muted" style="margin:.4rem 0 1rem">
        In short: 1) write the details below and save, 2) open <strong>Sections</strong> and build your sections and questions from scratch —
        each question can have any number of answers with its own scores, 3) set the result ranges here, 4) set Status to <em>Published</em>. The app scores everything automatically.
    </div>

    @if($questionnaire->exists)
        <div class="actions" style="margin-bottom:1rem">
            <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">Manage sections ({{ $sections->count() }})</a>
            <a class="button button-secondary" href="{{ route('admin.questionnaires.scoring', $questionnaire) }}">Scoring overview</a>
        </div>
        @if($sections->isEmpty())
            <p class="muted">No sections yet — this questionnaire uses flat additive scoring. Add sections to switch it to weighted wellbeing scoring.</p>
        @endif
    @endif

<form method="POST" action="{{ $questionnaire->exists ? route('admin.questionnaires.update', $questionnaire) : route('admin.questionnaires.store') }}">@csrf @if($questionnaire->exists)@method('PUT')@endif
<label>Title</label><input name="title" value="{{ old('title', $questionnaire->title) }}" required><label>Description</label><textarea name="description">{{ old('description', $questionnaire->description) }}</textarea>
<div class="field-row"><div><label>Type</label><select name="type"><option value="stress">Stress</option></select></div><div><label>Version</label><input name="version" type="number" min="1" value="{{ old('version', $questionnaire->version ?? 1) }}" required></div><div><label>Status</label><select name="status">@foreach(['draft','published','archived'] as $status)<option value="{{ $status }}" @selected(old('status', $questionnaire->status ?? 'draft') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></div></div>
<label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $questionnaire->is_active))> Active for students</label><label for="published_at">Go live at <span class="muted">(optional)</span></label><input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', optional($questionnaire->published_at)->format('Y-m-d\TH:i')) }}">

@if($questionnaire->exists && $availableQuestions->isNotEmpty())
<h2>Reuse existing questions <span class="muted">(optional)</span></h2><p class="muted">Tick a question from the bank to attach it, set its order, and (for weighted questionnaires) its section. To build fresh questions, use the Sections page instead.</p>
@php($selected = old('questions', $questionnaire->exists ? $questionnaire->questions->map(fn($q) => ['id'=>$q->id,'position'=>$q->pivot->position,'is_required'=>$q->pivot->is_required,'questionnaire_section_id'=>$q->pivot->questionnaire_section_id])->all() : []))
@foreach($availableQuestions as $question)
    @php($configured = collect($selected)->firstWhere('id', $question->id))
    <div class="field-row">
        <label class="remember"><input name="questions[{{ $question->id }}][id]" type="checkbox" value="{{ $question->id }}" @checked($configured) onchange="this.closest('.field-row').querySelectorAll('[data-membership]').forEach(i => i.disabled = !this.checked)"> {{ $question->question_text }}</label>
        <div><label>Position</label><input data-membership name="questions[{{ $question->id }}][position]" type="number" min="0" value="{{ $configured['position'] ?? $question->position }}" @disabled(!$configured)></div>
        @if($sections->isNotEmpty())
        <div><label>Section</label><select data-membership name="questions[{{ $question->id }}][questionnaire_section_id]" @disabled(!$configured)>
            <option value="">— none —</option>
            @foreach($sections as $section)<option value="{{ $section->id }}" @selected(($configured['questionnaire_section_id'] ?? null) == $section->id)>{{ $section->title }}</option>@endforeach
        </select></div>
        @endif
        <label class="remember"><input data-membership name="questions[{{ $question->id }}][is_required]" type="checkbox" value="1" @checked($configured['is_required'] ?? true) @disabled(!$configured)> Required</label>
    </div>
@endforeach
@endif

<h2>Result ranges</h2>
<p class="muted">Add as many ranges as you need. <strong>Overall</strong> ranges score the wellbeing result; <strong>Stress</strong> ranges (0–100) score the optional stress indicator. Ranges of the same scope must not overlap and must leave no gap. Rows removed here are deactivated.</p>
@php($bands = old('bands', $questionnaire->exists
        ? $questionnaire->scoreBands->map(fn($b) => $b->only(['id','scope','code','label','min_score','max_score','position','is_active']))->all()
        : [
            ['scope'=>'overall','code'=>'low','label'=>'Low mental well-being','min_score'=>0,'max_score'=>20,'position'=>1,'is_active'=>true],
            ['scope'=>'overall','code'=>'moderate','label'=>'Moderate mental well-being','min_score'=>21,'max_score'=>30,'position'=>2,'is_active'=>true],
            ['scope'=>'overall','code'=>'high','label'=>'High mental well-being','min_score'=>31,'max_score'=>40,'position'=>3,'is_active'=>true],
        ]))
<div id="band-rows">
@foreach($bands as $index => $band)
    <div class="field-row band-row">
        @if(isset($band['id']))<input type="hidden" name="bands[{{ $index }}][id]" value="{{ $band['id'] }}">@endif
        <div><label>Scope</label><select name="bands[{{ $index }}][scope]">
            <option value="overall" @selected(($band['scope'] ?? 'overall') === 'overall')>Overall wellbeing</option>
            <option value="stress" @selected(($band['scope'] ?? 'overall') === 'stress')>Stress</option>
        </select></div>
        <div><label>Code</label><input name="bands[{{ $index }}][code]" value="{{ $band['code'] }}" required></div>
        <div><label>Label</label><input name="bands[{{ $index }}][label]" value="{{ $band['label'] }}" required></div>
        <div style="max-width:100px"><label>Min</label><input type="number" name="bands[{{ $index }}][min_score]" value="{{ $band['min_score'] }}" required></div>
        <div style="max-width:100px"><label>Max</label><input type="number" name="bands[{{ $index }}][max_score]" value="{{ $band['max_score'] }}" required></div>
        <div style="max-width:90px"><label>Position</label><input type="number" min="0" name="bands[{{ $index }}][position]" value="{{ $band['position'] }}" required></div>
        <label class="remember"><input type="checkbox" name="bands[{{ $index }}][is_active]" value="1" @checked($band['is_active'] ?? false)> Active</label>
        <button type="button" class="button button-secondary band-remove" style="align-self:end">Remove</button>
    </div>
@endforeach
</div>
<button type="button" id="add-band" class="button button-secondary">＋ Add result range</button>

<div class="actions" style="margin-top:1.5rem"><button class="button">Save questionnaire</button><a class="button button-secondary" href="{{ route('admin.questionnaires.index') }}">Cancel</a></div>
</form>
</main>

<script>
(function () {
    var rows = document.getElementById('band-rows');
    var addBtn = document.getElementById('add-band');
    var nextIndex = {{ count($bands) }};

    function wireRow(row) {
        var remove = row.querySelector('.band-remove');
        if (remove) remove.addEventListener('click', function () { row.remove(); });
    }
    rows.querySelectorAll('.band-row').forEach(wireRow);

    addBtn.addEventListener('click', function () {
        var i = nextIndex++;
        var pos = rows.querySelectorAll('.band-row').length + 1;
        var div = document.createElement('div');
        div.className = 'field-row band-row';
        div.innerHTML =
            '<div><label>Scope</label><select name="bands[' + i + '][scope]"><option value="overall">Overall wellbeing</option><option value="stress">Stress</option></select></div>' +
            '<div><label>Code</label><input name="bands[' + i + '][code]" required></div>' +
            '<div><label>Label</label><input name="bands[' + i + '][label]" required></div>' +
            '<div style="max-width:100px"><label>Min</label><input type="number" name="bands[' + i + '][min_score]" required></div>' +
            '<div style="max-width:100px"><label>Max</label><input type="number" name="bands[' + i + '][max_score]" required></div>' +
            '<div style="max-width:90px"><label>Position</label><input type="number" min="0" name="bands[' + i + '][position]" value="' + pos + '" required></div>' +
            '<label class="remember"><input type="checkbox" name="bands[' + i + '][is_active]" value="1" checked> Active</label>' +
            '<button type="button" class="button button-secondary band-remove" style="align-self:end">Remove</button>';
        rows.appendChild(div);
        wireRow(div);
    });
})();
</script>
@endsection
