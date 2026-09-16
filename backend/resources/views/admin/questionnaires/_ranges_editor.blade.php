{{-- The same range editor stays on Details for existing questionnaires and
     appears on Scoring while a newly created draft is being set up. --}}
@php($bands = old('bands', $questionnaire->scoreBands->where('is_active', true)->count()
        ? $questionnaire->scoreBands->where('is_active', true)->map(fn ($b) => $b->only(['id','scope','code','label','min_score','max_score','position','is_active']) + ['intervention_id' => $primaryInterventionByBand[$b->id] ?? null])->values()->all()
        : [['scope'=>'overall','code'=>'','label'=>'','min_score'=>$scoreSpan[0],'max_score'=>'','position'=>1,'is_active'=>true,'intervention_id'=>null]]))
    {{-- ============ RESULT RANGES ============ --}}
    <section class="panel" id="ranges">
        <div class="panel-head">
            <div class="eyebrow">Results</div>
            <h2 style="margin:2px 0 4px">Result scale, ranges &amp; recommended support</h2>
            @if($resultScale)
                <p class="lede">A student's answers add up to a raw score, which is converted onto the result scale below — so the scale and its ranges stay the same however many questions there are. Ranges must not overlap, and together they must cover the whole scale.</p>
            @else
                <p class="lede">A student's answers add up to a score between <strong>{{ $scoreSpan[0] }}</strong> and <strong>{{ $scoreSpan[1] }}</strong>. Ranges must not overlap, and together they must cover every score.</p>
            @endif
        </div>

        <div class="meta-grid" style="margin-bottom:16px">
            <div class="tile"><span class="k">Questionnaire</span><span class="v">{{ $questionCount }}</span><span class="muted" style="font-size:.8rem">{{ \Illuminate\Support\Str::plural('question', $questionCount) }}</span></div>
            <div class="tile"><span class="k">Raw score</span><span class="v">{{ $rawSpan[0] }}–{{ $rawSpan[1] }}</span><span class="muted" style="font-size:.8rem">worked out from the answers</span></div>
            @if($resultScale)
                <div class="tile"><span class="k">Result scale</span><span class="v">{{ $resultScale[0] }}–{{ $resultScale[1] }}</span><span class="muted" style="font-size:.8rem">what students see</span></div>
            @endif
            <div class="tile"><span class="k">Result ranges</span><span class="v">{{ $overallBands->where('is_active', true)->count() }}</span><span class="muted" style="font-size:.8rem">configured</span></div>
        </div>

        @if($rangeProblems)
            <ul class="errors" style="margin-bottom:14px">
                @foreach($rangeProblems as $problem)<li>{{ $problem }}</li>@endforeach
            </ul>
        @else
            <p class="ready" style="margin-bottom:14px">✓ Every score from {{ $scoreSpan[0] }} to {{ $scoreSpan[1] }} has a range.</p>
        @endif

        <form method="POST" action="{{ route('admin.questionnaires.ranges', $questionnaire) }}">@csrf @method('PATCH')
            @if($resultScale)
                <div class="editor-row" style="margin-bottom:14px;background:#f7faf8;border-color:#b8dcd4">
                    <div class="fld grow" style="flex-basis:260px"><label>Result scale <span class="muted">— fixed by the client; the raw score is converted onto it</span></label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="number" name="result_scale_min" value="{{ old('result_scale_min', $resultScale[0]) }}" style="max-width:110px" required>
                            <span class="muted">to</span>
                            <input type="number" name="result_scale_max" value="{{ old('result_scale_max', $resultScale[1]) }}" style="max-width:110px" required>
                        </div>
                    </div>
                    <div class="fld grow"><span class="muted" style="font-size:.85rem;display:block;padding-bottom:6px">Example with the current questions: a raw score of {{ $rawSpan[1] > $rawSpan[0] ? (int) round(($rawSpan[0] + $rawSpan[1]) * 0.75) : $rawSpan[0] }} becomes {{ \App\Services\AssessmentScoringService::normalise(($rawSpan[0] + $rawSpan[1]) * 0.75, $rawSpan[0], $rawSpan[1], $resultScale) }} on the {{ $resultScale[0] }}–{{ $resultScale[1] }} scale.</span></div>
                </div>
            @endif
            <div id="band-rows">
            @foreach($bands as $index => $band)
                @php($scope = $band['scope'] ?? 'overall')
                <div class="editor-row band-row" @if($scope !== 'overall') data-stress @endif>
                    @if(isset($band['id']))<input type="hidden" name="bands[{{ $index }}][id]" value="{{ $band['id'] }}">@endif
                    <input type="hidden" name="bands[{{ $index }}][scope]" value="{{ $scope }}">
                    <input type="hidden" name="bands[{{ $index }}][code]" class="band-code" value="{{ $band['code'] }}">
                    <input type="hidden" name="bands[{{ $index }}][position]" class="band-position" value="{{ $band['position'] }}">
                    <input type="hidden" name="bands[{{ $index }}][is_active]" value="1">
                    @if($scope !== 'overall')<span class="badge" title="Stress indicator ranges are measured 0–100">stress 0–100</span>@endif
                    <div class="fld narrow"><label>From</label><input type="number" name="bands[{{ $index }}][min_score]" value="{{ $band['min_score'] }}" required></div>
                    <div class="fld narrow"><label>To</label><input type="number" name="bands[{{ $index }}][max_score]" value="{{ $band['max_score'] }}" required></div>
                    <div class="fld grow"><label>Result shown to the student</label><input class="band-label" name="bands[{{ $index }}][label]" value="{{ $band['label'] }}" placeholder="e.g. Low stress" required></div>
                    <div class="fld grow"><label>Recommend first</label><select name="bands[{{ $index }}][intervention_id]">
                        <option value="">— No specific item —</option>
                        @foreach($interventions->groupBy('content_type') as $type => $items)
                            <optgroup label="{{ \Illuminate\Support\Str::of($type)->replace('_', ' ')->title() }}">
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" @selected((int) ($band['intervention_id'] ?? 0) === $item->id)>{{ $item->title }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select></div>
                    <button type="button" class="button button-secondary row-remove band-remove">Remove</button>
                </div>
            @endforeach
            </div>
            <button type="button" id="add-band" class="button button-secondary add-row">＋ Add range</button>
            <div class="actions" style="margin-top:16px"><button class="button{{ $creationFlow ? ' button-secondary' : '' }}" type="submit">Save scale &amp; ranges</button>@if($creationFlow)<button class="button" type="submit" name="next" value="review">Continue to Review &amp; Publish <i data-lucide="arrow-right"></i></button>@endif</div>
        </form>
        <p class="muted" style="margin:14px 0 0;font-size:.85rem">Support items are managed under <a href="{{ route('admin.interventions.index') }}">Support Content</a> and <a href="{{ route('admin.positive-engagement.index') }}">Positive Engagement</a>. Items not tied to a particular range are shown for every result.</p>
    </section>

<script>
(function () {
    // Result ranges: rows come and go; code and order are derived so the
    // admin only ever types a score span and a name.
    var rows = document.getElementById('band-rows');
    var addBtn = document.getElementById('add-band');
    var interventionOptions = @json($interventions->groupBy('content_type')->map(fn ($items) => $items->map(fn ($i) => ['id' => $i->id, 'title' => $i->title])->values()));
    var nextIndex = {{ count($bands) }};
    function slug(t) { return t.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50) || 'range'; }
    function escapeHtml(t) { return String(t).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
    function renumber() {
        rows.querySelectorAll('.band-row').forEach(function (row, i) {
            row.querySelector('.band-position').value = i + 1;
        });
    }
    function wire(row) {
        var label = row.querySelector('.band-label'), code = row.querySelector('.band-code');
        if (label && code && !row.querySelector('input[name$="[id]"]')) {
            label.addEventListener('input', function () { code.value = slug(label.value) + '-' + Date.now().toString(36); });
            if (!code.value) code.value = 'range-' + Date.now().toString(36);
        }
        var r = row.querySelector('.band-remove');
        if (r) r.addEventListener('click', function () { row.remove(); renumber(); });
    }
    rows.querySelectorAll('.band-row').forEach(wire);
    addBtn.addEventListener('click', function () {
        var i = nextIndex++;
        var last = rows.querySelector('.band-row:last-child input[name$="[max_score]"]');
        var from = last && last.value !== '' ? Number(last.value) + 1 : 0;
        var groups = '';
        Object.keys(interventionOptions).forEach(function (type) {
            groups += '<optgroup label="' + escapeHtml(type.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); })) + '">';
            interventionOptions[type].forEach(function (o) { groups += '<option value="' + o.id + '">' + escapeHtml(o.title) + '</option>'; });
            groups += '</optgroup>';
        });
        var div = document.createElement('div');
        div.className = 'editor-row band-row';
        div.innerHTML =
            '<input type="hidden" name="bands[' + i + '][scope]" value="overall">' +
            '<input type="hidden" name="bands[' + i + '][code]" class="band-code" value="">' +
            '<input type="hidden" name="bands[' + i + '][position]" class="band-position" value="">' +
            '<input type="hidden" name="bands[' + i + '][is_active]" value="1">' +
            '<div class="fld narrow"><label>From</label><input type="number" name="bands[' + i + '][min_score]" value="' + from + '" required></div>' +
            '<div class="fld narrow"><label>To</label><input type="number" name="bands[' + i + '][max_score]" required></div>' +
            '<div class="fld grow"><label>Result shown to the student</label><input class="band-label" name="bands[' + i + '][label]" placeholder="e.g. Low stress" required></div>' +
            '<div class="fld grow"><label>Recommend first</label><select name="bands[' + i + '][intervention_id]"><option value="">— No specific item —</option>' + groups + '</select></div>' +
            '<button type="button" class="button button-secondary row-remove band-remove">Remove</button>';
        rows.appendChild(div);
        wire(div);
        renumber();
        div.querySelector('input[name$="[max_score]"]').focus();
    });
    renumber();
})();
</script>
