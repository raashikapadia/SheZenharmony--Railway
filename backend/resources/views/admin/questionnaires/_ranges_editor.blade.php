{{-- The result levels editor: each level is a range on the result scale,
     what the student is told, and the support recommended for it. Used by
     the Result levels step. Expects: $questionnaire, $scoreSpan, $resultScale,
     $rawSpan, $questionCount, $overallBands, $rangeProblems, $interventions,
     $primaryInterventionByBand, $interventionsByBand. --}}
@php($fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'))
@php($bands = old('bands', $questionnaire->scoreBands->where('is_active', true)->where('scope', 'overall')->sortBy('min_score')->count()
        ? $questionnaire->scoreBands->where('is_active', true)->where('scope', 'overall')->sortBy('min_score')->map(fn ($b) => $b->only(['id','scope','code','label','description','harmony_message','min_score','max_score','position','is_active']) + [
                'intervention_id' => $primaryInterventionByBand[$b->id] ?? null,
                'intervention_ids' => array_values(array_filter($interventionsByBand[$b->id] ?? [], fn ($id) => $id !== ($primaryInterventionByBand[$b->id] ?? null))),
            ])->values()->all()
        : [['scope'=>'overall','code'=>'','label'=>'','description'=>'','harmony_message'=>'','min_score'=>$scoreSpan[0],'max_score'=>'','position'=>1,'is_active'=>true,'intervention_id'=>null,'intervention_ids'=>[]]]))
@php($isPercent = $resultScale === [0, 100])
    <section class="panel" id="ranges">
        <div class="panel-head">
            <div class="eyebrow">Result levels</div>
            <h2 style="margin:2px 0 4px">What each result means &amp; what to recommend</h2>
            <p class="lede">A student's result lands between <strong>{{ $scoreSpan[0] }}</strong> and <strong>{{ $scoreSpan[1] }}</strong>{{ $isPercent ? ' (a percentage)' : '' }}. Add as many levels as you need — they must not overlap, and together they must cover every score. The level a result falls in decides the message shown and the support recommended.</p>
        </div>

        <div class="meta-grid" style="margin-bottom:16px">
            <div class="tile"><span class="k">Questions</span><span class="v">{{ $questionCount }}</span></div>
            <div class="tile"><span class="k">{{ $questionnaire->usesWeightedSections() ? 'Weighted total' : 'Raw points' }}</span><span class="v">{{ $rawSpan[0] }}–{{ $rawSpan[1] }}</span><span class="muted" style="font-size:.8rem">{{ $questionnaire->usesWeightedSections() ? 'up to the sum of section weights' : 'worked out from the answers' }}</span></div>
            <div class="tile"><span class="k">Result scale</span><span class="v">{{ $scoreSpan[0] }}–{{ $scoreSpan[1] }}</span><span class="muted" style="font-size:.8rem">{{ $isPercent ? 'percentage' : 'what students see' }} · <a href="{{ route('admin.questionnaires.scoring', $questionnaire) }}">change</a></span></div>
            <div class="tile"><span class="k">Result levels</span><span class="v">{{ $overallBands->where('is_active', true)->count() }}</span><span class="muted" style="font-size:.8rem">configured</span></div>
        </div>

        @if($rangeProblems)
            <ul class="errors" style="margin-bottom:14px">
                @foreach($rangeProblems as $problem)<li>{{ $problem }}</li>@endforeach
            </ul>
        @else
            <p class="ready" style="margin-bottom:14px">✓ Every score from {{ $scoreSpan[0] }} to {{ $scoreSpan[1] }} has a level.</p>
        @endif

        <form method="POST" action="{{ route('admin.questionnaires.ranges', $questionnaire) }}">@csrf @method('PATCH')
            <div id="band-rows" class="stack-sm">
            @foreach($bands as $index => $band)
                @php($scope = $band['scope'] ?? 'overall')
                @php($linked = collect($band['intervention_ids'] ?? [])->map(fn ($id) => (int) $id))
                <div class="band-card band-row">
                    @if(isset($band['id']))<input type="hidden" name="bands[{{ $index }}][id]" value="{{ $band['id'] }}">@endif
                    <input type="hidden" name="bands[{{ $index }}][scope]" value="{{ $scope }}">
                    <input type="hidden" name="bands[{{ $index }}][code]" class="band-code" value="{{ $band['code'] }}">
                    <input type="hidden" name="bands[{{ $index }}][position]" class="band-position" value="{{ $band['position'] }}">
                    <input type="hidden" name="bands[{{ $index }}][is_active]" value="1">
                    <div class="editor-row" style="border:0;padding:0;background:transparent">
                        <div class="fld narrow"><label>From</label><input type="number" name="bands[{{ $index }}][min_score]" value="{{ $band['min_score'] }}" required></div>
                        <div class="fld narrow"><label>To</label><input type="number" name="bands[{{ $index }}][max_score]" value="{{ $band['max_score'] }}" required></div>
                        <div class="fld grow"><label>Level name shown to the student</label><input class="band-label" name="bands[{{ $index }}][label]" value="{{ $band['label'] }}" placeholder="e.g. Moderate" required></div>
                        <button type="button" class="button button-secondary row-remove band-remove">Remove</button>
                    </div>
                    <div class="field-row" style="margin-top:8px">
                        <div><label>Short description <span class="muted">(optional)</span></label><input name="bands[{{ $index }}][description]" value="{{ $band['description'] ?? '' }}" placeholder="e.g. Some signs of strain worth keeping an eye on"></div>
                        <div><label>Result message <span class="muted">(optional — shown with the result)</span></label><input name="bands[{{ $index }}][harmony_message]" value="{{ $band['harmony_message'] ?? '' }}" placeholder="e.g. Consider speaking with a counsellor."></div>
                    </div>
                    <div class="field-row" style="margin-top:8px">
                        <div><label>Recommend first</label><select name="bands[{{ $index }}][intervention_id]">
                            <option value="">— No specific item —</option>
                            @foreach($interventions->groupBy('content_type') as $type => $items)
                                <optgroup label="{{ \Illuminate\Support\Str::of($type)->replace('_', ' ')->title() }}">
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" @selected((int) ($band['intervention_id'] ?? 0) === $item->id)>{{ $item->title }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select></div>
                        <div><label>Also recommend <span class="muted">(hold Ctrl / ⌘ to pick several)</span></label>
                            <input type="hidden" name="bands[{{ $index }}][intervention_ids][]" value="">
                            <select name="bands[{{ $index }}][intervention_ids][]" multiple size="3">
                            @foreach($interventions->groupBy('content_type') as $type => $items)
                                <optgroup label="{{ \Illuminate\Support\Str::of($type)->replace('_', ' ')->title() }}">
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" @selected($linked->contains($item->id))>{{ $item->title }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select></div>
                    </div>
                </div>
            @endforeach
            </div>
            <button type="button" id="add-band" class="button button-secondary add-row">＋ Add level</button>
            <div class="split" style="margin-top:16px;align-items:center">
                <a class="button button-secondary" href="{{ route('admin.questionnaires.scoring', $questionnaire) }}">← Scoring</a>
                <div class="actions"><button class="button button-secondary" type="submit">Save</button><button class="button" type="submit" name="next" value="review">Save &amp; continue to Review &amp; Publish <i data-lucide="arrow-right"></i></button></div>
            </div>
        </form>
        <p class="muted" style="margin:14px 0 0;font-size:.85rem">Support items are managed under <a href="{{ route('admin.interventions.index') }}">Support Content</a> and <a href="{{ route('admin.positive-engagement.index') }}">Positive Engagement</a>. Items not tied to any level are shown for every result.</p>
    </section>

<style>
    .band-card{padding:12px 14px;border:1px solid #dce7f5;border-radius:12px;background:#fff}
    .band-card select[multiple]{min-height:70px}
</style>
<script>
(function () {
    // Result levels: rows come and go; code and order are derived so the
    // admin only ever types a score span, a name and a message.
    var rows = document.getElementById('band-rows');
    var addBtn = document.getElementById('add-band');
    var interventionOptions = @json($interventions->groupBy('content_type')->map(fn ($items) => $items->map(fn ($i) => ['id' => $i->id, 'title' => $i->title])->values()));
    var nextIndex = {{ count($bands) }};
    var ceiling = {{ (int) $scoreSpan[1] }};
    function slug(t) { return t.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 50) || 'level'; }
    function escapeHtml(t) { return String(t).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }); }
    function renumber() {
        rows.querySelectorAll('.band-row').forEach(function (row, i) {
            row.querySelector('.band-position').value = i + 1;
        });
    }
    function groups(multiple) {
        var html = '';
        Object.keys(interventionOptions).forEach(function (type) {
            html += '<optgroup label="' + escapeHtml(type.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); })) + '">';
            interventionOptions[type].forEach(function (o) { html += '<option value="' + o.id + '">' + escapeHtml(o.title) + '</option>'; });
            html += '</optgroup>';
        });
        return html;
    }
    function wire(row) {
        var label = row.querySelector('.band-label'), code = row.querySelector('.band-code');
        if (label && code && !row.querySelector('input[name$="[id]"]')) {
            label.addEventListener('input', function () { code.value = slug(label.value) + '-' + Date.now().toString(36); });
            if (!code.value) code.value = 'level-' + Date.now().toString(36);
        }
        var r = row.querySelector('.band-remove');
        if (r) r.addEventListener('click', function () { row.remove(); renumber(); });
    }
    rows.querySelectorAll('.band-row').forEach(wire);
    addBtn.addEventListener('click', function () {
        var i = nextIndex++;
        var last = rows.querySelector('.band-row:last-child input[name$="[max_score]"]');
        var from = last && last.value !== '' ? Number(last.value) + 1 : {{ (int) $scoreSpan[0] }};
        var div = document.createElement('div');
        div.className = 'band-card band-row';
        div.innerHTML =
            '<input type="hidden" name="bands[' + i + '][scope]" value="overall">' +
            '<input type="hidden" name="bands[' + i + '][code]" class="band-code" value="">' +
            '<input type="hidden" name="bands[' + i + '][position]" class="band-position" value="">' +
            '<input type="hidden" name="bands[' + i + '][is_active]" value="1">' +
            '<div class="editor-row" style="border:0;padding:0;background:transparent">' +
            '<div class="fld narrow"><label>From</label><input type="number" name="bands[' + i + '][min_score]" value="' + from + '" required></div>' +
            '<div class="fld narrow"><label>To</label><input type="number" name="bands[' + i + '][max_score]" value="' + (from < ceiling ? ceiling : '') + '" required></div>' +
            '<div class="fld grow"><label>Level name shown to the student</label><input class="band-label" name="bands[' + i + '][label]" placeholder="e.g. High" required></div>' +
            '<button type="button" class="button button-secondary row-remove band-remove">Remove</button></div>' +
            '<div class="field-row" style="margin-top:8px">' +
            '<div><label>Short description <span class="muted">(optional)</span></label><input name="bands[' + i + '][description]" placeholder="e.g. Some signs of strain worth keeping an eye on"></div>' +
            '<div><label>Result message <span class="muted">(optional — shown with the result)</span></label><input name="bands[' + i + '][harmony_message]" placeholder="e.g. Consider speaking with a counsellor."></div></div>' +
            '<div class="field-row" style="margin-top:8px">' +
            '<div><label>Recommend first</label><select name="bands[' + i + '][intervention_id]"><option value="">— No specific item —</option>' + groups() + '</select></div>' +
            '<div><label>Also recommend <span class="muted">(hold Ctrl / ⌘ to pick several)</span></label><input type="hidden" name="bands[' + i + '][intervention_ids][]" value=""><select name="bands[' + i + '][intervention_ids][]" multiple size="3">' + groups() + '</select></div></div>';
        rows.appendChild(div);
        wire(div);
        renumber();
        div.querySelector('.band-label').focus();
    });
    renumber();
})();
</script>
