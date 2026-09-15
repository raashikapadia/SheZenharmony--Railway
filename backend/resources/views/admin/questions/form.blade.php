@extends('layouts.admin')
@php($scoped = isset($section) && isset($questionnaire))
@php($backUrl = $scoped ? route('admin.questionnaires.sections.index', $questionnaire).'#section-'.$section->id : route('admin.questions.index'))
@php($formAction = $scoped
    ? ($question->exists ? route('admin.questionnaires.sections.questions.update', [$questionnaire, $section, $question]) : route('admin.questionnaires.sections.questions.store', [$questionnaire, $section]))
    : ($question->exists ? route('admin.questions.update', $question) : route('admin.questions.store')))
@php($presets = \App\Support\AnswerScalePresets::all())
@php($defaultPreset = $presets[\App\Support\AnswerScalePresets::DEFAULT])
@php($options = old('options', $question->exists
        ? $question->options->where('is_active', true)->sortBy('position')->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'value' => $o->value, 'score' => $o->score])->values()->all()
        : $defaultPreset['options']))
@php($type = old('question_type', $question->question_type ?? 'scale'))
@section('title', $question->exists ? 'Edit question' : 'Add question')
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ $backUrl }}">← {{ $scoped ? 'Back to '.$questionnaire->title : 'Questions' }}</a>
    <div>
        @if($scoped)<div class="eyebrow">Section · {{ $section->title }}</div>@endif
        <h1 style="margin:2px 0 0">{{ $question->exists ? 'Edit question' : 'Add question' }}</h1>
    </div>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

<section class="panel form-panel" style="max-width:820px">
<form id="question-form" class="stack" method="POST" action="{{ $formAction }}">@csrf @if($question->exists) @method('PUT') @endif

    <div class="stack-sm">
        <div><label for="question_text">Question</label><textarea id="question_text" name="question_text" rows="2" placeholder="e.g. How have you been feeling lately?" required autofocus>{{ old('question_text', $question->question_text) }}</textarea></div>
        <div class="field-row">
            <div style="max-width:320px"><label for="question_type">Question type</label><select id="question_type" name="question_type">
                @foreach(\App\Enums\QuestionType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select></div>
            @unless($scoped)
            <div style="max-width:160px"><label for="position">Position</label><input id="position" name="position" type="number" min="0" value="{{ old('position', $question->position ?? 0) }}" required></div>
            @endunless
        </div>
    </div>

    {{-- Answer options: the block adapts to the question type. --}}
    <div>
        <div class="split" style="align-items:flex-end;gap:12px">
            <div>
                <h3 style="margin:0 0 4px">Answer options</h3>
                <p class="lede" id="answers-hint"></p>
            </div>
            <div id="scale-picker" style="min-width:260px"><label for="scale-preset">Scale</label><select id="scale-preset">
                @foreach($presets as $key => $preset)
                    @if($preset['type'] === 'scale')<option value="{{ $key }}">{{ $preset['label'] }}</option>@endif
                @endforeach
                <option value="">Custom scale</option>
            </select></div>
        </div>

        <div id="option-rows" class="stack-sm" style="margin-top:12px">
        @foreach($options as $index => $option)
            <div class="editor-row option-row">
                @if(isset($option['id']))<input name="options[{{ $index }}][id]" type="hidden" value="{{ $option['id'] }}">@endif
                <div class="reorder">
                    <button type="button" class="arrow opt-up" title="Move up" aria-label="Move answer up">▲</button>
                    <button type="button" class="arrow opt-down" title="Move down" aria-label="Move answer down">▼</button>
                </div>
                <div class="fld grow"><label>Answer</label><input class="opt-label" name="options[{{ $index }}][label]" type="text" value="{{ $option['label'] }}" required></div>
                <div class="fld narrow"><label>Points</label><input class="opt-score" name="options[{{ $index }}][score]" type="number" value="{{ $option['score'] }}"></div>
                <input class="opt-value" name="options[{{ $index }}][value]" type="hidden" value="{{ $option['value'] }}">
                <button type="button" class="button button-secondary row-remove opt-remove">Remove</button>
            </div>
        @endforeach
        </div>
        <button type="button" id="add-option" class="button button-secondary add-row">＋ Add answer option</button>
    </div>

    <div class="actions">
        @if($scoped)<label class="remember"><input name="is_required" type="checkbox" value="1" @checked(old('is_required', $isRequired ?? true))> Students must answer this question</label>@endif
    </div>

    {{-- Everything below is for the scoring engine and reporting. The
         defaults suit a standard question, so it stays folded away. --}}
    <input type="hidden" name="dimension" value="{{ old('dimension', $question->dimension ?? ($scoped ? $section->title : '')) }}">
    <details class="advanced" @if($errors->hasAny(['min_score','max_score','wellbeing_weight','stress_weight','help_text'])) open @endif>
        <summary>More options</summary>
        <div class="stack-sm" style="margin-top:12px">
            <div><label for="help_text">Note for students <span class="muted">(optional)</span></label>
            <input id="help_text" name="help_text" type="text" value="{{ old('help_text', $question->help_text) }}"></div>
            <div class="actions">
                <label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $question->exists ? $question->is_active : true))> Shown to students</label>
                <label class="remember"><input name="is_sensitive" type="checkbox" value="1" @checked(old('is_sensitive', $question->is_sensitive))> Sensitive topic</label>
                <label class="remember"><input name="is_reverse_scored" type="checkbox" value="1" @checked(old('is_reverse_scored', $question->is_reverse_scored))> Reverse scored <span class="muted">— a high answer means lower wellbeing</span></label>
            </div>
            <div class="field-row">
                <div><label for="wellbeing_weight">Weight within its section</label><input id="wellbeing_weight" name="wellbeing_weight" type="number" step="0.01" min="0" value="{{ old('wellbeing_weight', $question->wellbeing_weight ?? 1) }}"></div>
                <div><label for="min_score">Lowest points <span class="muted">(blank = from answers)</span></label><input id="min_score" name="min_score" type="number" value="{{ old('min_score', $question->min_score) }}"></div>
                <div><label for="max_score">Highest points <span class="muted">(blank = from answers)</span></label><input id="max_score" name="max_score" type="number" value="{{ old('max_score', $question->max_score) }}"></div>
            </div>
            <div class="field-row">
                <div><label class="remember"><input name="stress_relevant" type="checkbox" value="1" @checked(old('stress_relevant', $question->stress_relevant))> Counts toward the stress indicator</label></div>
                <div><label for="stress_direction">Stress direction</label><select id="stress_direction" name="stress_direction">
                    <option value="{{ \App\Models\StressQuestion::STRESS_DIRECTION_MORE }}" @selected(old('stress_direction', $question->stress_direction) === \App\Models\StressQuestion::STRESS_DIRECTION_MORE)>Higher answer = more stress</option>
                    <option value="{{ \App\Models\StressQuestion::STRESS_DIRECTION_LESS }}" @selected(old('stress_direction', $question->stress_direction) === \App\Models\StressQuestion::STRESS_DIRECTION_LESS)>Higher answer = less stress</option>
                </select></div>
                <div><label for="stress_weight">Stress weight</label><input id="stress_weight" name="stress_weight" type="number" step="0.01" min="0" value="{{ old('stress_weight', $question->stress_weight ?? 1) }}"></div>
            </div>
        </div>
    </details>

    <div class="actions" style="justify-content:flex-end">
        <a class="button button-secondary" href="{{ $backUrl }}">Cancel</a>
        <button class="button" type="submit">Save question</button>
    </div>
</form>
</section>
</main>

<script>
(function () {
    var rows = document.getElementById('option-rows');
    var addBtn = document.getElementById('add-option');
    var preset = document.getElementById('scale-preset');
    var scalePicker = document.getElementById('scale-picker');
    var typeSelect = document.getElementById('question_type');
    var hint = document.getElementById('answers-hint');
    var presets = @json($presets);
    var nextIndex = {{ count($options) }};
    var hints = {
        scale: 'Pick a ready-made scale or adjust the answers below. Points are what each answer is worth.',
        multiple_choice: 'List the choices a student can pick from — one answer each. Points are what each answer is worth.',
        yes_no: 'Two answers, Yes and No. Set the points each one is worth.'
    };

    function slug(t) { return t.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 100); }
    function escapeHtml(t) { return String(t).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
    function allRows() { return Array.prototype.slice.call(rows.querySelectorAll('.option-row')); }

    function refreshArrows() {
        var list = allRows();
        list.forEach(function (row, i) {
            row.querySelector('.opt-up').disabled = i === 0;
            row.querySelector('.opt-down').disabled = i === list.length - 1;
        });
        // Yes/No is exactly two fixed answers; anything else can grow.
        var yesNo = typeSelect.value === 'yes_no';
        addBtn.hidden = yesNo;
        list.forEach(function (row) { row.querySelector('.opt-remove').hidden = yesNo; });
    }

    function wire(row) {
        var label = row.querySelector('.opt-label'), value = row.querySelector('.opt-value');
        var isExisting = !!row.querySelector('input[type=hidden][name$="[id]"]');
        // A new answer's stored key follows its wording; a saved one keeps
        // the key it has so past responses still match up.
        if (!isExisting) {
            label.addEventListener('input', function () { value.value = slug(label.value); });
            if (!value.value && label.value) value.value = slug(label.value);
        }
        row.querySelector('.opt-remove').addEventListener('click', function () {
            if (allRows().length <= 2) { alert('A question needs at least two answer options.'); return; }
            row.remove();
            preset.value = '';
            refreshArrows();
        });
        row.querySelector('.opt-up').addEventListener('click', function () {
            var prev = row.previousElementSibling;
            if (prev) rows.insertBefore(row, prev);
            refreshArrows();
        });
        row.querySelector('.opt-down').addEventListener('click', function () {
            var next = row.nextElementSibling;
            if (next) rows.insertBefore(next, row);
            refreshArrows();
        });
    }

    function addRow(label, value, score) {
        var i = nextIndex++;
        var div = document.createElement('div');
        div.className = 'editor-row option-row';
        div.innerHTML =
            '<div class="reorder"><button type="button" class="arrow opt-up" title="Move up" aria-label="Move answer up">▲</button><button type="button" class="arrow opt-down" title="Move down" aria-label="Move answer down">▼</button></div>' +
            '<div class="fld grow"><label>Answer</label><input class="opt-label" name="options[' + i + '][label]" type="text" value="' + escapeHtml(label || '') + '" required></div>' +
            '<div class="fld narrow"><label>Points</label><input class="opt-score" name="options[' + i + '][score]" type="number" value="' + escapeHtml(score == null ? '' : score) + '"></div>' +
            '<input class="opt-value" name="options[' + i + '][value]" type="hidden" value="' + escapeHtml(value || '') + '">' +
            '<button type="button" class="button button-secondary row-remove opt-remove">Remove</button>';
        rows.appendChild(div);
        wire(div);
        refreshArrows();
        return div;
    }

    function replaceRows(options) {
        rows.innerHTML = '';
        options.forEach(function (o) { addRow(o.label, o.value, o.score); });
    }

    function confirmReplace() {
        var hasSaved = rows.querySelector('input[type=hidden][name$="[id]"]');
        return !hasSaved || confirm('Replace the current answers? Past responses keep their original wording in reports.');
    }

    // The block follows the question type: a scale picker for rating
    // scales, free rows for multiple choice, a fixed Yes / No pair.
    function applyType(initial) {
        var type = typeSelect.value;
        hint.textContent = hints[type] || '';
        scalePicker.hidden = type !== 'scale';
        if (!initial) {
            if (type === 'yes_no') {
                var labels = allRows().map(function (r) { return r.querySelector('.opt-label').value.trim().toLowerCase(); }).join('|');
                if (labels !== 'no|yes' && labels !== 'yes|no' && confirmReplace()) replaceRows(presets.yes_no.options);
            } else if (type === 'scale' && preset.value && presets[preset.value] && confirmReplace()) {
                replaceRows(presets[preset.value].options);
            }
        }
        refreshArrows();
    }

    allRows().forEach(wire);

    addBtn.addEventListener('click', function () {
        if (allRows().length >= 20) { alert('A question can have at most 20 answer options.'); return; }
        addRow('', '', '').querySelector('.opt-label').focus();
        preset.value = '';
    });

    preset.addEventListener('change', function () {
        var chosen = presets[preset.value];
        if (!chosen) return;
        if (!confirmReplace()) { preset.value = ''; return; }
        replaceRows(chosen.options);
    });

    typeSelect.addEventListener('change', function () { applyType(false); });
    rows.addEventListener('input', function () { preset.value = ''; });

    // Start with the picker reflecting whatever the rows already are.
    var current = allRows().map(function (r) { return r.querySelector('.opt-label').value.trim(); }).join('|');
    preset.value = '';
    Object.keys(presets).forEach(function (key) {
        if (presets[key].type === 'scale' && presets[key].options.map(function (o) { return o.label; }).join('|') === current) preset.value = key;
    });
    applyType(true);
})();
</script>
@endsection
