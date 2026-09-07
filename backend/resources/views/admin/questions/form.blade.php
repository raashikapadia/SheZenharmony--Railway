@extends('layouts.admin')
@php($scoped = isset($section) && isset($questionnaire))
@php($backUrl = $scoped ? route('admin.questionnaires.sections.index', $questionnaire) : route('admin.questions.index'))
@php($formAction = $scoped
    ? ($question->exists ? route('admin.questionnaires.sections.questions.update', [$questionnaire, $section, $question]) : route('admin.questionnaires.sections.questions.store', [$questionnaire, $section]))
    : ($question->exists ? route('admin.questions.update', $question) : route('admin.questions.store')))
@php($options = old('options', $question->exists
        ? $question->options->where('is_active', true)->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'value' => $o->value, 'score' => $o->score])->values()->all()
        : [
            ['label' => 'Strongly disagree', 'value' => 'strongly_disagree', 'score' => 1],
            ['label' => 'Disagree', 'value' => 'disagree', 'score' => 2],
            ['label' => 'Neutral', 'value' => 'neutral', 'score' => 3],
            ['label' => 'Agree', 'value' => 'agree', 'score' => 4],
            ['label' => 'Strongly agree', 'value' => 'strongly_agree', 'score' => 5],
        ]))
@section('title', $question->exists ? 'Edit question' : 'Add question')
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ $backUrl }}">← {{ $scoped ? 'Back to editor' : 'Questions' }}</a>
    <div>
        <h1 style="margin:0">{{ $question->exists ? 'Edit question' : 'Add question' }}@if($scoped) <span class="muted" style="font-size:1rem;font-family:inherit">· {{ $section->title }}</span>@endif</h1>
    </div>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

<form class="stack" method="POST" action="{{ $formAction }}">@csrf @if($question->exists) @method('PUT') @endif

    <div class="stack-sm">
        <div><label for="question_text">Question text</label><textarea id="question_text" name="question_text" required>{{ old('question_text', $question->question_text) }}</textarea></div>
        <div class="field-row">
            <div><label for="dimension">Dimension <span class="muted">(label only)</span></label><input id="dimension" name="dimension" type="text" value="{{ old('dimension', $question->dimension ?? ($scoped ? $section->title : '')) }}"></div>
            @unless($scoped)
            <div><label for="position">Position</label><input id="position" name="position" type="number" min="0" value="{{ old('position', $question->position ?? 0) }}" required></div>
            @endunless
            <div><label for="question_type">Type</label><select id="question_type" name="question_type">
                @foreach(\App\Enums\QuestionType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('question_type', $question->question_type ?? 'scale') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select></div>
        </div>
        <div><label for="help_text">Help text <span class="muted">(optional guidance shown with the question)</span></label>
        <input id="help_text" name="help_text" type="text" value="{{ old('help_text', $question->help_text) }}"></div>
        <div class="actions">
            <label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $question->exists ? $question->is_active : true))> Active</label>
            <label class="remember"><input name="is_sensitive" type="checkbox" value="1" @checked(old('is_sensitive', $question->is_sensitive))> Sensitive content</label>
            @if($scoped)<label class="remember"><input name="is_required" type="checkbox" value="1" @checked(old('is_required', $isRequired ?? true))> Required in this questionnaire</label>@endif
        </div>
    </div>

    <div>
        <h3>Answer options</h3>
        <p class="lede">Between 2 and 20 options. Add or remove rows to build any scale you need — “Score” is the number each answer is worth.</p>
        <div id="option-rows">
        @foreach($options as $index => $option)
            <div class="editor-row option-row">
                @if(isset($option['id']))<input name="options[{{ $index }}][id]" type="hidden" value="{{ $option['id'] }}">@endif
                <div class="fld grow"><label>Label</label><input class="opt-label" name="options[{{ $index }}][label]" type="text" value="{{ $option['label'] }}" required></div>
                <div class="fld"><label>Value</label><input class="opt-value" name="options[{{ $index }}][value]" type="text" value="{{ $option['value'] }}" required></div>
                <div class="fld narrow"><label>Score</label><input name="options[{{ $index }}][score]" type="number" value="{{ $option['score'] }}"></div>
                <button type="button" class="button button-secondary row-remove opt-remove">Remove</button>
            </div>
        @endforeach
        </div>
        <button type="button" id="add-option" class="button button-secondary add-row">＋ Add answer option</button>
    </div>

    <details class="advanced">
        <summary>Advanced scoring settings</summary>
        <p class="lede" style="margin:.8rem 0 0">These only take effect for questionnaires that use weighted sections. Defaults are fine for a standard rating question.</p>
        <div class="field-row" style="margin-top:14px">
            <div><label for="min_score">Minimum score</label><input id="min_score" name="min_score" type="number" value="{{ old('min_score', $question->min_score) }}" placeholder="auto from options"><span class="muted">Leave blank to derive from the option scores.</span></div>
            <div><label for="max_score">Maximum score</label><input id="max_score" name="max_score" type="number" value="{{ old('max_score', $question->max_score) }}" placeholder="auto from options"></div>
            <div><label for="wellbeing_weight">Question weight</label><input id="wellbeing_weight" name="wellbeing_weight" type="number" step="0.01" min="0" value="{{ old('wellbeing_weight', $question->wellbeing_weight ?? 1) }}"><span class="muted">How strongly it counts within its section.</span></div>
        </div>
        <label class="remember"><input name="is_reverse_scored" type="checkbox" value="1" @checked(old('is_reverse_scored', $question->is_reverse_scored))> Reverse scoring <span class="muted">— higher answers contribute less positively to wellbeing.</span></label>
        <div class="field-row" style="margin-top:10px">
            <div><label class="remember"><input name="stress_relevant" type="checkbox" value="1" @checked(old('stress_relevant', $question->stress_relevant))> Contributes to stress score</label></div>
            <div><label for="stress_direction">Stress direction</label><select id="stress_direction" name="stress_direction">
                <option value="{{ \App\Models\StressQuestion::STRESS_DIRECTION_MORE }}" @selected(old('stress_direction', $question->stress_direction) === \App\Models\StressQuestion::STRESS_DIRECTION_MORE)>Higher answer = more stress</option>
                <option value="{{ \App\Models\StressQuestion::STRESS_DIRECTION_LESS }}" @selected(old('stress_direction', $question->stress_direction) === \App\Models\StressQuestion::STRESS_DIRECTION_LESS)>Higher answer = less stress</option>
            </select></div>
            <div><label for="stress_weight">Stress weight</label><input id="stress_weight" name="stress_weight" type="number" step="0.01" min="0" value="{{ old('stress_weight', $question->stress_weight ?? 1) }}"></div>
        </div>
    </details>

    <div class="actions"><button class="button" type="submit">Save question</button><a class="button button-secondary" href="{{ $backUrl }}">Cancel</a></div>
</form>
</main>

<script>
(function () {
    var rows = document.getElementById('option-rows');
    var addBtn = document.getElementById('add-option');
    var nextIndex = {{ count($options) }};
    function slug(t) { return t.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 100); }
    function wire(row) {
        var label = row.querySelector('.opt-label'), value = row.querySelector('.opt-value');
        if (label && value) label.addEventListener('blur', function () {
            if (!value.value.trim() && label.value.trim()) value.value = slug(label.value);
        });
        var rm = row.querySelector('.opt-remove');
        if (rm) rm.addEventListener('click', function () {
            if (rows.querySelectorAll('.option-row').length <= 2) { alert('A question needs at least two answer options.'); return; }
            row.remove();
        });
    }
    rows.querySelectorAll('.option-row').forEach(wire);
    addBtn.addEventListener('click', function () {
        if (rows.querySelectorAll('.option-row').length >= 20) { alert('A question can have at most 20 answer options.'); return; }
        var i = nextIndex++;
        var div = document.createElement('div');
        div.className = 'editor-row option-row';
        div.innerHTML =
            '<div class="fld grow"><label>Label</label><input class="opt-label" name="options[' + i + '][label]" type="text" required></div>' +
            '<div class="fld"><label>Value</label><input class="opt-value" name="options[' + i + '][value]" type="text" required></div>' +
            '<div class="fld narrow"><label>Score</label><input name="options[' + i + '][score]" type="number"></div>' +
            '<button type="button" class="button button-secondary row-remove opt-remove">Remove</button>';
        rows.appendChild(div);
        wire(div);
    });
})();
</script>
@endsection
