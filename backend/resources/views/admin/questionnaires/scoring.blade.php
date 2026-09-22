@extends('layouts.admin')
@section('title', 'Scoring · '.$questionnaire->title)
@section('body')
@php($fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'))
@php($method = old('scoring_method', $questionnaire->scoringMethod()))
@php($weighting = old('section_weighting', $questionnaire->usesEqualSectionWeights() ? 'equal' : 'custom'))
@php($basis = old('result_basis', $resultScale === [0, 100] || $resultScale === null ? 'percentage' : 'scale'))
@php($activeSections = $questionnaire->sections->where('is_active', true)->values())
@php($sectionRows = collect($overview['sections'])->keyBy('id'))
@php($typeLabels = collect(\App\Enums\QuestionType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
<main class="content stack questionnaire-creation-step">
    <a class="backlink" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">← Back to Sections &amp; Questions</a>

    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <div>
        <div class="eyebrow">Step 3 of 5 · Scoring</div>
        <h1 style="margin:0 0 4px">Configure scoring</h1>
        <p class="lede">How answers become a result. Everything on the right is worked out for you from the sections and questions you have built — change a setting and it updates.</p>
    </div>

    @include('admin.questionnaires._creation_progress', ['questionnaire' => $questionnaire, 'review' => $review, 'step' => 3])

    @if($validationError)
        <div class="errors"><strong>Needs attention before publishing:</strong> {{ $validationError }}</div>
    @endif

    <div class="scoring-layout">
    <form class="stack" method="POST" action="{{ route('admin.questionnaires.scoring.update', $questionnaire) }}" id="scoring-form">@csrf @method('PATCH')

        {{-- ============ METHOD ============ --}}
        <section class="panel">
            <div class="panel-head"><h2 style="margin:0">How the overall result is calculated</h2></div>
            <div class="choice-cards">
                <label class="choice-card {{ $method === 'weighted_sections' ? 'selected' : '' }}">
                    <input type="radio" name="scoring_method" value="weighted_sections" @checked($method === 'weighted_sections')>
                    <span><strong>Weighted sections <small class="badge">recommended</small></strong>
                    <small>Each section scores as a share of its own maximum, then contributes its weight. Long and short sections count fairly; the maximum total is the sum of the weights.</small>
                    <code>section score = (points ÷ section maximum) × weight · result = sum of section scores</code></span>
                </label>
                <label class="choice-card {{ $method === 'points_total' ? 'selected' : '' }}">
                    <input type="radio" name="scoring_method" value="points_total" @checked($method === 'points_total')>
                    <span><strong>Points total</strong>
                    <small>Every answer's points are added up (question weights and scoring direction applied). Sections only shape the breakdown, so a longer section counts for more.</small>
                    <code>result = sum of every answer's points</code></span>
                </label>
            </div>
        </section>

        {{-- ============ SECTION WEIGHTS ============ --}}
        <section class="panel">
            <div class="panel-head"><h2 style="margin:0">Section weights</h2></div>
            @if($activeSections->isEmpty())
                <p class="lede">No sections yet — <a href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">add sections and questions</a> first, and their weights will appear here.</p>
                <input type="hidden" name="section_weighting" value="{{ $weighting }}">
            @else
                <div class="radio-row" style="margin-bottom:12px">
                    <label class="remember"><input type="radio" name="section_weighting" value="equal" @checked($weighting === 'equal')> Equal — every section counts the same <span class="muted">(worked out automatically)</span></label>
                    <label class="remember"><input type="radio" name="section_weighting" value="custom" @checked($weighting === 'custom')> Custom — I'll set each section's weight</label>
                </div>
                <div class="table-wrap"><table id="weights-table">
                    <thead><tr><th>#</th><th>Section</th><th>Questions</th><th>Points possible</th><th>Weight</th><th>Share of result</th></tr></thead>
                    <tbody>
                    @foreach($activeSections as $i => $section)
                        @php($row = $sectionRows->get($section->id))
                        <tr data-section-row>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $section->title }}</td>
                            <td>{{ $row['questions'] ?? $section->questions_count }}</td>
                            <td>{{ $row ? $fmt($row['min_raw']).'–'.$fmt($row['max_raw']) : '—' }}</td>
                            <td>
                                <input type="hidden" name="sections[{{ $i }}][id]" value="{{ $section->id }}">
                                <input class="weight-input" type="number" step="0.01" min="0.01" name="sections[{{ $i }}][category_weight]" value="{{ old("sections.$i.category_weight", $fmt($section->category_weight)) }}" data-stored="{{ $fmt($section->category_weight) }}" style="max-width:110px" @if($weighting === 'equal') disabled @endif>
                            </td>
                            <td class="weight-share">{{ $row ? $fmt($row['weight_share']).'%' : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="4">Total</th><th class="weight-total">{{ $fmt($overview['weight_total']) }}</th><th>100%</th></tr></tfoot>
                </table></div>
                <p class="muted" style="margin:10px 0 0;font-size:.85rem" id="weights-hint"></p>
            @endif
        </section>

        {{-- ============ RESULT SCALE ============ --}}
        <section class="panel">
            <div class="panel-head"><h2 style="margin:0">How the result is reported</h2></div>
            <div class="radio-row" style="margin-bottom:10px">
                <label class="remember"><input type="radio" name="result_basis" value="percentage" @checked($basis === 'percentage')> Percentage (0–100) <span class="muted">— result levels are written as percentages</span></label>
                <label class="remember"><input type="radio" name="result_basis" value="scale" @checked($basis === 'scale')> A fixed scale of my own</label>
            </div>
            <div class="editor-row" id="scale-fields" @if($basis !== 'scale') hidden @endif style="background:#f7faf8;border-color:#b8dcd4">
                <div class="fld narrow"><label>From</label><input type="number" name="result_scale_min" value="{{ old('result_scale_min', $resultScale && $resultScale !== [0, 100] ? $resultScale[0] : 0) }}"></div>
                <div class="fld narrow"><label>To</label><input type="number" name="result_scale_max" value="{{ old('result_scale_max', $resultScale && $resultScale !== [0, 100] ? $resultScale[1] : '') }}" placeholder="e.g. 40"></div>
                <div class="fld grow"><span class="muted" style="font-size:.85rem;display:block;padding-bottom:6px">The total is converted onto this scale, so it holds still however many questions there are. Result levels are written on it.</span></div>
            </div>
            @if($overallBands->where('is_active', true)->isNotEmpty())
                <p class="muted" style="margin:10px 0 0;font-size:.85rem">Changing the scale means re-checking the result levels on the next step — they must cover the new range.</p>
            @endif
        </section>

        <div class="split" style="align-items:center">
            <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">← Sections &amp; questions</a>
            <div class="actions">
                <button class="button button-secondary" type="submit">Save</button>
                <button class="button" type="submit" name="next" value="results">Save &amp; continue to Result levels <i data-lucide="arrow-right"></i></button>
            </div>
        </div>
    </form>

    {{-- ============ AUTOMATIC FIGURES ============ --}}
    <aside class="stack-sm">
        <section class="panel">
            <div class="panel-head"><h2 style="margin:0">Assessment overview</h2></div>
            <p class="muted" style="margin:0 0 10px;font-size:.85rem">Calculated live from the current configuration — nothing here is typed in.</p>
            <div class="meta-grid overview-grid">
                <div class="tile"><span class="k">Sections</span><span class="v">{{ $overview['section_count'] }}</span></div>
                <div class="tile"><span class="k">Questions</span><span class="v">{{ $overview['question_count'] }}</span></div>
                <div class="tile"><span class="k">Scoring</span><span class="v" style="font-size:1rem">{{ $questionnaire->scoringMethodLabel() }}</span><span class="muted" style="font-size:.8rem">{{ $questionnaire->usesEqualSectionWeights() ? 'equal weights' : 'custom weights' }}</span></div>
                <div class="tile"><span class="k">Maximum score</span><span class="v">{{ $overview['total_span'][1] }}</span><span class="muted" style="font-size:.8rem">{{ $questionnaire->usesWeightedSections() && $overview['section_count'] ? 'sum of section weights' : 'points total' }}</span></div>
                <div class="tile"><span class="k">Raw points</span><span class="v">{{ $rawSpan[0] }}–{{ $rawSpan[1] }}</span><span class="muted" style="font-size:.8rem">from the answers</span></div>
                <div class="tile"><span class="k">Result scale</span><span class="v">{{ $overview['result_span'][0] }}–{{ $overview['result_span'][1] }}</span><span class="muted" style="font-size:.8rem">{{ $resultScale === [0, 100] ? 'percentage' : 'what students see' }}</span></div>
                <div class="tile"><span class="k">Percentage range</span><span class="v">{{ $fmt($overview['percentage_range'][0]) }}–{{ $fmt($overview['percentage_range'][1]) }}%</span><span class="muted" style="font-size:.8rem">lowest to highest possible</span></div>
                <div class="tile"><span class="k">Result levels</span><span class="v">{{ $overallBands->where('is_active', true)->count() }}</span><span class="muted" style="font-size:.8rem"><a href="{{ route('admin.questionnaires.result-levels', $questionnaire) }}">configure</a></span></div>
            </div>
            @if($overview['question_types'])
                <p class="muted" style="margin:12px 0 0;font-size:.85rem">Question types: {{ collect($overview['question_types'])->map(fn ($n, $t) => $n.' × '.($typeLabels[$t] ?? $t))->join(', ') }}.</p>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head"><h2 style="margin:0">Question score ranges</h2></div>
            @if($questionRows->isEmpty())
                <p class="lede">No questions yet.</p>
            @else
                @foreach($activeSections as $section)
                    @php($qs = $questionRows->get($section->id, collect()))
                    @if($qs->isNotEmpty())
                        <div class="eyebrow" style="margin-top:8px">{{ $section->title }}</div>
                        <ul class="answer-list" style="margin:4px 0 0">
                            @foreach($qs as $q)
                                <li><span class="answer-dot"></span><span style="flex:1;min-width:0;overflow-wrap:anywhere">{{ \Illuminate\Support\Str::limit($q['text'], 70) }}</span>
                                    <span class="muted" style="white-space:nowrap">{{ $q['min'] ?? '?' }}–{{ $q['max'] ?? '?' }} pts{{ $q['weight'] !== 1.0 ? ' × '.$fmt($q['weight']) : '' }}{{ $q['reversed'] ? ' ↓' : '' }}{{ $q['answer_mode'] === 'multiple' ? ' ☑' : '' }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
                <p class="muted" style="margin:10px 0 0;font-size:.8rem">↓ = higher answer scores lower · ☑ = several answers allowed · × = question weight</p>
            @endif
        </section>

        @if($stressBands->isNotEmpty())
        <section class="panel">
            <div class="panel-head"><h3 style="margin:0">Stress indicator ranges <span class="muted">(0–100)</span></h3></div>
            @include('admin.questionnaires._band_table', ['bands' => $stressBands, 'ceiling' => 100])
        </section>
        @endif
    </aside>
    </div>
</main>

<style>
    .scoring-layout{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(300px,.85fr);gap:18px;align-items:start}
    .choice-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.choice-card{display:flex;gap:10px;align-items:flex-start;padding:14px;border:1px solid #d7e4f1;border-radius:12px;background:#fff;cursor:pointer}.choice-card.selected{border-color:#2877be;box-shadow:0 0 0 3px rgba(40,119,190,.12)}.choice-card input{margin-top:4px}.choice-card strong{display:flex;align-items:center;gap:8px;color:#183b5c;font-size:.95rem}.choice-card strong .badge{font-size:.65rem}.choice-card small{display:block;margin-top:4px;color:#60728b;font-size:.82rem;line-height:1.4}.choice-card code{display:block;margin-top:8px;padding:6px 8px;border-radius:6px;background:#f3f7fb;color:#2b4d70;font-size:.75rem}
    .radio-row{display:flex;flex-wrap:wrap;gap:16px;align-items:center}
    .overview-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    #scale-fields[hidden]{display:none}
    @media(max-width:980px){.scoring-layout{grid-template-columns:1fr}}
    @media(max-width:640px){.choice-cards{grid-template-columns:1fr}}
</style>
<script>
(function () {
    var form = document.getElementById('scoring-form');
    if (!form) return;
    var cards = form.querySelectorAll('.choice-card');
    var weightInputs = Array.prototype.slice.call(form.querySelectorAll('.weight-input'));
    var shares = Array.prototype.slice.call(form.querySelectorAll('.weight-share'));
    var total = form.querySelector('.weight-total');
    var hint = document.getElementById('weights-hint');
    var scaleFields = document.getElementById('scale-fields');
    function fmt(n) { return String(Math.round(n * 100) / 100); }

    function applyWeighting() {
        var chosen = form.querySelector('input[name=section_weighting]:checked');
        var equal = !chosen || chosen.value === 'equal';
        var n = weightInputs.length;
        weightInputs.forEach(function (input) {
            input.disabled = equal;
            if (equal) input.value = fmt(100 / n); else if (!input.value) input.value = input.dataset.stored || '1';
        });
        recalc();
        if (hint) hint.textContent = equal
            ? 'Each of the ' + n + ' sections is worth ' + fmt(100 / n) + ' — ' + fmt(100 / n) + '% of the result. Add or remove a section and this updates by itself.'
            : 'Weights are compared with each other: 20 / 30 / 50 gives 20%, 30% and 50% of the result. Use any numbers you like — the shares are worked out for you.';
    }
    function recalc() {
        var sum = weightInputs.reduce(function (s, i) { return s + (Number(i.value) || 0); }, 0);
        weightInputs.forEach(function (input, idx) {
            shares[idx].textContent = sum > 0 ? fmt((Number(input.value) || 0) / sum * 100) + '%' : '—';
        });
        if (total) total.textContent = fmt(sum);
    }
    function applyMethod() {
        cards.forEach(function (c) { c.classList.toggle('selected', c.querySelector('input').checked); });
    }
    function applyBasis() {
        var chosen = form.querySelector('input[name=result_basis]:checked');
        var scale = chosen && chosen.value === 'scale';
        if (scaleFields) {
            scaleFields.hidden = !scale;
            scaleFields.querySelectorAll('input').forEach(function (i) { i.required = scale; });
        }
    }
    form.querySelectorAll('input[name=section_weighting]').forEach(function (r) { r.addEventListener('change', applyWeighting); });
    form.querySelectorAll('input[name=scoring_method]').forEach(function (r) { r.addEventListener('change', applyMethod); });
    form.querySelectorAll('input[name=result_basis]').forEach(function (r) { r.addEventListener('change', applyBasis); });
    weightInputs.forEach(function (i) { i.addEventListener('input', recalc); });
    // Disabled inputs are not posted; the server derives equal weights, so
    // re-enable them on submit only so their values travel for display.
    form.addEventListener('submit', function () { weightInputs.forEach(function (i) { i.disabled = false; }); });
    applyMethod(); applyWeighting(); applyBasis();
})();
</script>
@endsection
