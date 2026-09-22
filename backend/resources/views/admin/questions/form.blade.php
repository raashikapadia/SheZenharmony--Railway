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
@php($answerMode = old('answer_mode', $question->answer_mode ?? 'single'))
@php($scoringMethod = old('scoring_method', $question->scoring_method ?? 'direct'))
@php($reversed = (bool) old('is_reverse_scored', $question->is_reverse_scored ?? false))
@section('title', $question->exists ? 'Edit question' : 'Add question')
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ $backUrl }}">← {{ $scoped ? 'Back to '.$questionnaire->title : 'Questions' }}</a>
    <div>
        @if($scoped)<div class="eyebrow">Section · {{ $section->title }}</div>@endif
        <h1 style="margin:2px 0 0">{{ $question->exists ? 'Edit question' : 'Add question' }}</h1>
    </div>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

<section class="panel form-panel" style="max-width:860px">
<form id="question-form" class="stack" method="POST" action="{{ $formAction }}">@csrf @if($question->exists) @method('PUT') @endif

    {{-- ============ 1. THE QUESTION ============ --}}
    <div class="stack-sm">
        <div><label for="question_text">Question</label><textarea id="question_text" name="question_text" rows="2" placeholder="e.g. How often do you feel overwhelmed?" required autofocus>{{ old('question_text', $question->question_text) }}</textarea></div>
        <div class="field-row">
            <div style="max-width:340px"><label for="question_type">Question type</label><select id="question_type" name="question_type">
                @foreach(\App\Enums\QuestionType::cases() as $case)
                    <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $case->label() }}</option>
                @endforeach
            </select></div>
            @unless($scoped)
            <div style="max-width:160px"><label for="position">Position</label><input id="position" name="position" type="number" min="0" value="{{ old('position', $question->position ?? 0) }}" required></div>
            @endunless
        </div>
    </div>

    {{-- ============ 2. ANSWER CONFIGURATION ============ --}}
    <div class="q-block">
        <div class="split" style="align-items:flex-end;gap:12px">
            <div>
                <h3 style="margin:0 0 4px">Answer configuration</h3>
                <p class="lede" id="answers-hint"></p>
            </div>
            <div id="scale-picker" style="min-width:260px"><label for="scale-preset">Ready-made scale</label><select id="scale-preset">
                @foreach($presets as $key => $preset)
                    @if($preset['type'] === 'scale')<option value="{{ $key }}">{{ $preset['label'] }}</option>@endif
                @endforeach
                <option value="">Custom scale</option>
            </select></div>
        </div>

        {{-- Rating scales are usually built from a range: 1–5, 1–10, 0–4 … --}}
        <div id="scale-builder" class="scale-builder">
            <div class="fld narrow"><label for="sb-min">From</label><input id="sb-min" type="number" value="1"></div>
            <div class="fld narrow"><label for="sb-max">To</label><input id="sb-max" type="number" value="5"></div>
            <div class="fld narrow"><label for="sb-step">Step</label><input id="sb-step" type="number" min="1" value="1"></div>
            <button type="button" class="button button-secondary" id="sb-apply">Build scale</button>
            <span class="muted" style="font-size:.85rem">Each point becomes an answer with that many points; add a label to each below.</span>
        </div>

        {{-- Multiple choice: one or several answers. --}}
        <div id="answer-mode" class="stack-sm" style="margin-top:12px">
            <label>Students can choose</label>
            <div class="radio-row">
                <label class="remember"><input type="radio" name="answer_mode" value="single" @checked($answerMode !== 'multiple')> One answer</label>
                <label class="remember"><input type="radio" name="answer_mode" value="multiple" @checked($answerMode === 'multiple')> Several answers</label>
                <span id="max-selections-wrap" class="inline-field"><label for="max_selections" class="muted">up to</label><input id="max_selections" name="max_selections" type="number" min="1" max="50" value="{{ old('max_selections', $question->max_selections) }}" placeholder="all" style="max-width:80px"></span>
            </div>
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

    {{-- ============ 3. SCORING ============ --}}
    <div class="q-block">
        <h3 style="margin:0 0 4px">Scoring</h3>
        <p class="lede">The points above are what each answer is worth. These two settings say how they are used.</p>

        <div id="scoring-method-wrap" class="stack-sm" style="margin-top:10px">
            <label for="scoring_method">Points for a multi-answer question come from</label>
            <select id="scoring_method" name="scoring_method" style="max-width:420px">
                <option value="direct" @selected($scoringMethod === 'direct')>The ticked answers' points added up</option>
                <option value="count_selected" @selected($scoringMethod === 'count_selected')>One point per ticked answer (points above are ignored)</option>
                <option value="max_selected" @selected($scoringMethod === 'max_selected')>The highest-scoring ticked answer only</option>
            </select>
        </div>

        <div class="stack-sm" style="margin-top:12px">
            <label>Scoring direction</label>
            <div class="radio-row">
                <label class="remember"><input type="radio" name="is_reverse_scored" value="0" @checked(! $reversed)> Higher answer = higher score</label>
                <label class="remember"><input type="radio" name="is_reverse_scored" value="1" @checked($reversed)> Higher answer = lower score <span class="muted">— for positively worded items, e.g. “I feel calm most of the time”</span></label>
            </div>
            <p class="muted" style="margin:0;font-size:.85rem" id="direction-example"></p>
        </div>
    </div>

    <div class="actions">
        @if($scoped)<label class="remember"><input name="is_required" type="checkbox" value="1" @checked(old('is_required', $isRequired ?? true))> Students must answer this question</label>@endif
    </div>

    {{-- Everything below is for the scoring engine and reporting. The
         defaults suit a standard question, so it stays folded away. --}}
    <input type="hidden" name="dimension" value="{{ old('dimension', $question->dimension ?? ($scoped ? $section->title : '')) }}">
    <details class="advanced" @if($errors->hasAny(['min_score','max_score','wellbeing_weight','stress_weight','help_text'])) open @endif>
        <summary>Advanced scoring</summary>
        <div class="stack-sm" style="margin-top:12px">
            <div><label for="help_text">Note for students <span class="muted">(optional)</span></label>
            <input id="help_text" name="help_text" type="text" value="{{ old('help_text', $question->help_text) }}"></div>
            <div class="actions">
                <label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $question->exists ? $question->is_active : true))> Shown to students</label>
                <label class="remember"><input name="is_sensitive" type="checkbox" value="1" @checked(old('is_sensitive', $question->is_sensitive))> Sensitive topic</label>
            </div>
            <div class="field-row">
                <div><label for="wellbeing_weight">Question weight within its section <span class="muted">(1 = same as the others)</span></label><input id="wellbeing_weight" name="wellbeing_weight" type="number" step="0.01" min="0" value="{{ old('wellbeing_weight', $question->wellbeing_weight ?? 1) }}"></div>
                <div><label for="min_score">Lowest points <span class="muted">(blank = worked out from answers)</span></label><input id="min_score" name="min_score" type="number" value="{{ old('min_score', $question->min_score) }}"></div>
                <div><label for="max_score">Highest points <span class="muted">(blank = worked out from answers)</span></label><input id="max_score" name="max_score" type="number" value="{{ old('max_score', $question->max_score) }}"></div>
            </div>
            <div class="field-row">
                <div><label class="remember"><input name="stress_relevant" type="checkbox" value="1" @checked(old('stress_relevant', $question->stress_relevant))> Counts toward the optional stress indicator</label></div>
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

<style>
    .q-block{padding:16px;border:1px solid #e2eaf2;border-radius:12px;background:#fbfdff}
    .radio-row{display:flex;flex-wrap:wrap;gap:16px;align-items:center}
    .inline-field{display:inline-flex;align-items:center;gap:6px}
    .scale-builder{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-top:12px;padding:10px 12px;border:1px dashed #c9dbeb;border-radius:10px;background:#fff}
    .scale-builder[hidden],#answer-mode[hidden],#scoring-method-wrap[hidden],#scale-picker[hidden],#max-selections-wrap[hidden]{display:none}
</style>

<script>
(function () {
    var rows = document.getElementById('option-rows');
    var addBtn = document.getElementById('add-option');
    var preset = document.getElementById('scale-preset');
    var scalePicker = document.getElementById('scale-picker');
    var scaleBuilder = document.getElementById('scale-builder');
    var answerMode = document.getElementById('answer-mode');
    var maxWrap = document.getElementById('max-selections-wrap');
    var scoringWrap = document.getElementById('scoring-method-wrap');
    var typeSelect = document.getElementById('question_type');
    var hint = document.getElementById('answers-hint');
    var directionExample = document.getElementById('direction-example');
    var presets = @json($presets);
    var nextIndex = {{ count($options) }};
    var hints = {
        scale: 'Build the scale from a range (1–5, 1–10, 0–4 …) or pick a ready-made one, then label each point. Points are what each answer is worth.',
        multiple_choice: 'List the choices a student can pick from. Points are what each answer is worth — they need not be in order.',
        yes_no: 'Two answers, e.g. True / False or Yes / No. Set the points each one is worth.'
    };

    function slug(t) { return t.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 100); }
    function escapeHtml(t) { return String(t).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
    function allRows() { return Array.prototype.slice.call(rows.querySelectorAll('.option-row')); }
    function isMultiple() { var r = document.querySelector('input[name=answer_mode]:checked'); return r && r.value === 'multiple'; }

    function refreshArrows() {
        var list = allRows();
        list.forEach(function (row, i) {
            row.querySelector('.opt-up').disabled = i === 0;
            row.querySelector('.opt-down').disabled = i === list.length - 1;
        });
        // True/False is exactly two fixed answers; anything else can grow.
        var yesNo = typeSelect.value === 'yes_no';
        addBtn.hidden = yesNo;
        list.forEach(function (row) { row.querySelector('.opt-remove').hidden = yesNo; });
        refreshDirectionExample();
    }

    // Shows the admin what "higher answer = lower score" does to their points.
    function refreshDirectionExample() {
        var scores = allRows().map(function (r) { return Number(r.querySelector('.opt-score').value); }).filter(function (n) { return !isNaN(n); });
        if (!scores.length) { directionExample.textContent = ''; return; }
        var min = Math.min.apply(null, scores), max = Math.max.apply(null, scores);
        var reversed = document.querySelector('input[name=is_reverse_scored]:checked');
        directionExample.textContent = reversed && reversed.value === '1'
            ? 'With this direction an answer worth ' + max + ' counts as ' + min + ', and one worth ' + min + ' counts as ' + max + '.'
            : 'Answers count exactly the points shown (' + min + ' to ' + max + ').';
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
        row.querySelector('.opt-score').addEventListener('input', refreshDirectionExample);
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

    // Build a numeric scale: one answer per step from "From" to "To", worth
    // that many points. Existing labels are kept where the points match.
    document.getElementById('sb-apply').addEventListener('click', function () {
        var min = Number(document.getElementById('sb-min').value);
        var max = Number(document.getElementById('sb-max').value);
        var step = Math.max(1, Number(document.getElementById('sb-step').value) || 1);
        if (isNaN(min) || isNaN(max) || max <= min) { alert('"To" must be higher than "From".'); return; }
        if ((max - min) / step + 1 > 20) { alert('A scale can have at most 20 points.'); return; }
        if (!confirmReplace()) return;
        var existing = {};
        allRows().forEach(function (r) { existing[r.querySelector('.opt-score').value] = r.querySelector('.opt-label').value; });
        var options = [];
        for (var v = min; v <= max; v += step) {
            var label = existing[String(v)] || String(v);
            options.push({ label: label, value: slug(label) || ('point_' + v), score: v });
        }
        replaceRows(options);
        preset.value = '';
    });

    // The blocks follow the question type: a scale builder for rating
    // scales, free rows plus one/several for multiple choice, a fixed
    // True / False pair.
    function applyType(initial) {
        var type = typeSelect.value;
        hint.textContent = hints[type] || '';
        scalePicker.hidden = type !== 'scale';
        scaleBuilder.hidden = type !== 'scale';
        answerMode.hidden = type !== 'multiple_choice';
        if (type !== 'multiple_choice') {
            var single = document.querySelector('input[name=answer_mode][value=single]');
            if (single) single.checked = true;
        }
        if (!initial) {
            if (type === 'yes_no') {
                var labels = allRows().map(function (r) { return r.querySelector('.opt-label').value.trim().toLowerCase(); }).join('|');
                if (labels !== 'no|yes' && labels !== 'yes|no' && labels !== 'false|true' && labels !== 'true|false' && confirmReplace()) replaceRows(presets.yes_no.options);
            } else if (type === 'scale' && preset.value && presets[preset.value] && confirmReplace()) {
                replaceRows(presets[preset.value].options);
            }
        }
        applyAnswerMode();
        refreshArrows();
    }

    function applyAnswerMode() {
        var multiple = isMultiple();
        maxWrap.hidden = !multiple;
        scoringWrap.hidden = !multiple;
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
    document.querySelectorAll('input[name=answer_mode]').forEach(function (r) { r.addEventListener('change', applyAnswerMode); });
    document.querySelectorAll('input[name=is_reverse_scored]').forEach(function (r) { r.addEventListener('change', refreshDirectionExample); });
    rows.addEventListener('input', function () { preset.value = ''; });

    // Start with the picker reflecting whatever the rows already are, and
    // the scale builder showing the current range.
    var current = allRows().map(function (r) { return r.querySelector('.opt-label').value.trim(); }).join('|');
    preset.value = '';
    Object.keys(presets).forEach(function (key) {
        if (presets[key].type === 'scale' && presets[key].options.map(function (o) { return o.label; }).join('|') === current) preset.value = key;
    });
    var startScores = allRows().map(function (r) { return Number(r.querySelector('.opt-score').value); }).filter(function (n) { return !isNaN(n); });
    if (startScores.length) {
        document.getElementById('sb-min').value = Math.min.apply(null, startScores);
        document.getElementById('sb-max').value = Math.max.apply(null, startScores);
    }
    applyType(true);
})();
</script>
@endsection
