@extends('layouts.admin')
@section('title', 'Preview · '.$questionnaire->title)
@section('body')
@php($sorted = $questionnaire->sections->sortBy('position')->values())
@php($unsectioned = ($questionsBySection[null] ?? ($questionsBySection[''] ?? collect())))
@php($total = $questionnaire->questions->count())
@php($previewAnswers = $previewAnswers ?? [])
@php($previewResult = $previewResult ?? null)
@php($previewError = $previewError ?? null)
@php($fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'))
@php($pages = $sorted->filter(fn ($s) => ($questionsBySection[$s->id] ?? collect())->isNotEmpty())->values())
@php($chosen = function (int $questionId) use ($previewAnswers): array {
    $v = $previewAnswers[$questionId] ?? null;
    return $v === null ? [] : array_map('intval', is_array($v) ? $v : [$v]);
})
@php($number = 0)

<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.review', $questionnaire) }}">← Back to Review &amp; publish</a>
    <div class="split" style="align-items:flex-start">
        <div>
            <h1 style="margin:0 0 4px">Preview</h1>
            <p class="lede">This is <strong>{{ $questionnaire->title }}</strong> (v{{ $questionnaire->version }}, {{ $questionnaire->publishState() }}) as a student experiences it: section by section, with the same answer controls. Answer it and press <em>See my result</em> to run the answers through the real scoring engine — <strong>nothing is saved</strong>.</p>
        </div>
        <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">Edit</a>
    </div>

    @if($previewError)
        <div class="errors"><strong>The scoring engine could not produce a result:</strong> {{ $previewError }}<br><span class="muted">This is exactly what a student would hit — fix the configuration before publishing.</span></div>
    @endif

    @if($previewResult)
        @php($band = $previewResult['band'])
        @php($bd = $previewResult['breakdown'])
        <section class="panel" style="border-color:#b8dcd4;background:#f7fcfa">
            <div class="eyebrow">Example result</div>
            <h2 style="margin:2px 0 6px">{{ $band->label }}</h2>
            @if($band->description)<p class="lede" style="margin:0 0 6px">{{ $band->description }}</p>@endif
            @if($band->harmony_message)<p style="margin:0 0 12px">{{ $band->harmony_message }}</p>@endif
            <div class="meta-grid">
                <div class="tile"><span class="k">Result</span><span class="v">{{ $previewResult['total_score'] }}@if($previewResult['result_scale']) <span class="muted" style="font-size:.9rem">/ {{ $previewResult['result_scale'][1] }}</span>@endif</span></div>
                @if($previewResult['percentage'] !== null)<div class="tile"><span class="k">Percentage</span><span class="v">{{ $fmt($previewResult['percentage']) }}%</span></div>@endif
                @if($bd)<div class="tile"><span class="k">Total</span><span class="v">{{ $fmt($bd['overall']['total']) }}<span class="muted" style="font-size:.9rem"> / {{ $fmt($bd['overall']['total_max']) }}</span></span><span class="muted" style="font-size:.8rem">{{ $bd['method'] === 'weighted_sections' ? 'weighted sections' : 'points total' }}</span></div>@endif
            </div>
            @if($bd && $bd['categories'])
                <div class="table-wrap" style="margin-top:12px"><table>
                    <thead><tr><th>Section</th><th>Points</th><th>Share</th><th>Weight</th><th>Weighted</th></tr></thead>
                    <tbody>
                    @foreach($bd['categories'] as $c)
                        <tr><td>{{ $c['title'] }}</td><td>{{ $fmt($c['raw_score']) }} / {{ $fmt($c['max_possible_score']) }}</td><td>{{ $fmt($c['percentage']) }}%</td><td>{{ $fmt($c['category_weight']) }}</td><td>{{ $fmt($c['weighted_score']) }} / {{ $fmt($c['category_weight']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
            @if($previewResult['recommendations']->isNotEmpty())
                <div class="eyebrow" style="margin-top:14px">Recommended support for this level</div>
                <ul class="answer-list" style="margin-top:4px">
                    @foreach($previewResult['recommendations'] as $item)
                        <li><span class="answer-dot"></span><strong>{{ $item->title }}</strong>@if($item->description) <span class="muted">— {{ \Illuminate\Support\Str::limit($item->description, 90) }}</span>@endif</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <form method="POST" action="{{ route('admin.questionnaires.preview.submit', $questionnaire) }}" id="sz-form">@csrf
    <div class="sz-frame">
        <div class="sz-notch"></div>
        <div class="sz-screen">
            <div class="sz-appbar">{{ $questionnaire->title }}</div>

            {{-- Intro --}}
            <div class="sz-view" id="sz-intro" @if($previewResult || $previewError) hidden @endif>
                <div class="sz-avatar">♥</div>
                <h2 class="sz-h">{{ $questionnaire->title }}</h2>
                <p class="sz-sub">{{ $questionnaire->description ?: 'Choose the response that feels most true for you right now.' }}</p>
                <div class="sz-card sz-intro-card">
                    <div class="sz-row"><span class="sz-ic">≣</span><span>{{ $pages->count() }} {{ \Illuminate\Support\Str::plural('section', $pages->count()) }} · {{ $total }} {{ \Illuminate\Support\Str::plural('question', $total) }}@if($questionnaire->estimated_minutes) · about {{ $questionnaire->estimated_minutes }} min @endif</span></div>
                    <div class="sz-row"><span class="sz-ic">🔒</span><span>Answers are saved with your SheZen ID, not your university email</span></div>
                    <div class="sz-row"><span class="sz-ic">＋</span><span>Your result supports reflection and is not a medical diagnosis</span></div>
                </div>
                <button class="sz-btn" type="button" onclick="szStart()" @disabled($total === 0)>Begin →</button>
                @if($total === 0)<p class="sz-sub" style="margin-top:12px">Add sections and questions before this can be taken.</p>@endif
            </div>

            {{-- One page per section --}}
            <div class="sz-view" id="sz-quiz" @unless($previewResult || $previewError) hidden @endunless>
                <div class="sz-qhead">
                    <span class="sz-qcount" id="sz-qcount"></span>
                    <span class="sz-qpct" id="sz-pct">0%</span>
                </div>
                <div class="sz-progress"><span id="sz-bar" style="width:0"></span></div>

                @foreach($pages as $pi => $section)
                    <div class="sz-page" data-page="{{ $pi }}" @if($pi > 0) hidden @endif>
                        <div class="sz-section"><span class="sz-eyebrow">Section {{ $pi + 1 }} of {{ $pages->count() }}</span><h3>{{ $section->title }}</h3>@if($section->description)<p class="sz-sub">{{ $section->description }}</p>@endif</div>
                        @foreach($questionsBySection[$section->id] as $question)
                            @php($number++)
                            @php($multi = $question->allowsMultipleAnswers())
                            @php($picked = $chosen($question->id))
                            <div class="sz-card sz-q" data-required="{{ $question->pivot->is_required ? 1 : 0 }}" data-multi="{{ $multi ? 1 : 0 }}" data-max="{{ $multi ? $question->selectionLimit() : 1 }}">
                                <p class="sz-qtext"><span class="sz-num">{{ $number }}</span>{{ $question->question_text }}@unless($question->pivot->is_required)<span class="sz-optional"> optional</span>@endunless</p>
                                @if($question->help_text)<p class="sz-sub" style="margin:-4px 0 8px 30px">{{ $question->help_text }}</p>@endif
                                @if($multi)<p class="sz-sub" style="margin:-4px 0 8px 30px">Choose {{ $question->selectionLimit() < $question->options->count() ? 'up to '.$question->selectionLimit() : 'all that apply' }}.</p>@endif
                                <div class="sz-opts">
                                    @forelse($question->options as $option)
                                        <label class="sz-opt {{ in_array($option->id, $picked, true) ? 'on' : '' }}">
                                            @if($multi)
                                                <input type="checkbox" name="answers[{{ $question->id }}][]" value="{{ $option->id }}" @checked(in_array($option->id, $picked, true))>
                                            @else
                                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}" @checked(in_array($option->id, $picked, true))>
                                            @endif
                                            <span class="sz-radio">{{ $multi ? '☐' : '○' }}</span><span>{{ $option->label }}</span>
                                        </label>
                                    @empty
                                        <p class="sz-sub">This question has no answer options yet.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                @if($unsectioned->isNotEmpty())
                    <p class="sz-sub" style="color:#b4562d">{{ $unsectioned->count() }} {{ \Illuminate\Support\Str::plural('question', $unsectioned->count()) }} not in any section are hidden from students.</p>
                @endif

                <div class="sz-nav">
                    <button type="button" class="sz-btn sz-btn-ghost" id="sz-prev" onclick="szGo(-1)">Previous</button>
                    <button type="button" class="sz-btn" id="sz-next" onclick="szGo(1)">Next</button>
                    <button type="submit" class="sz-btn" id="sz-submit" hidden>See my result</button>
                </div>
                <p class="sz-sub" style="text-align:center;margin-top:12px">Preview only — no result is saved.</p>
            </div>
        </div>
    </div>
    </form>
</main>

<style>
    .sz-frame{max-width:420px;margin:0 auto;padding:14px;border-radius:38px;background:#1d2733;box-shadow:0 20px 50px rgba(20,40,60,.3)}.sz-notch{width:120px;height:18px;margin:0 auto 8px;border-radius:0 0 14px 14px;background:#0f1620}.sz-screen{min-height:640px;padding:16px;border-radius:26px;background:#f7f5fb;color:#2a2f3a;font-family:system-ui,sans-serif}
    .sz-appbar{margin:-16px -16px 14px;padding:14px 18px;border-radius:26px 26px 0 0;background:#fff;color:#3b2f6b;font-weight:700;text-align:center;box-shadow:0 1px 0 #ece8f5}
    .sz-avatar{width:64px;height:64px;margin:20px auto 12px;border-radius:50%;display:grid;place-items:center;background:#ece6fa;color:#7a5cc9;font-size:1.6rem}.sz-h{margin:0;text-align:center;font-size:1.25rem;color:#2b2650}.sz-sub{margin:6px 0 0;color:#6b6f80;font-size:.86rem;line-height:1.45;text-align:center}
    .sz-card{margin-top:14px;padding:14px;border:1px solid #e6e2f2;border-radius:16px;background:#fff}.sz-intro-card .sz-row{display:flex;gap:10px;align-items:flex-start;margin:6px 0;font-size:.85rem;color:#4b4f5e}.sz-ic{width:22px;flex:0 0 22px;text-align:center;color:#7a5cc9}
    .sz-btn{display:block;width:100%;margin-top:16px;padding:13px;border:0;border-radius:14px;background:#7a5cc9;color:#fff;font-weight:700;cursor:pointer}.sz-btn:disabled{opacity:.5;cursor:not-allowed}.sz-btn-ghost{background:#fff;color:#5a4a99;border:1px solid #d9d1f0}
    .sz-qhead{display:flex;justify-content:space-between;font-size:.8rem;color:#6b6f80}.sz-progress{height:6px;margin:6px 0 4px;border-radius:3px;background:#e9e4f5;overflow:hidden}.sz-progress span{display:block;height:100%;background:#7a5cc9;transition:width .2s}
    .sz-section{margin-top:12px}.sz-eyebrow{display:block;color:#7a5cc9;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase}.sz-section h3{margin:2px 0 0;color:#2b2650}.sz-section .sz-sub{text-align:left}
    .sz-qtext{display:flex;gap:8px;align-items:flex-start;margin:0 0 10px;font-weight:600;color:#2b2f3a;line-height:1.4}.sz-num{display:grid;place-items:center;width:22px;height:22px;flex:0 0 22px;border-radius:50%;background:#ece6fa;color:#7a5cc9;font-size:.75rem}.sz-optional{color:#8b8fa0;font-weight:500;font-size:.8rem}
    .sz-opts{display:grid;gap:8px}.sz-opt{display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid #e3def0;border-radius:12px;background:#faf9fd;cursor:pointer;font-size:.9rem;text-align:left}.sz-opt input{position:absolute;opacity:0;pointer-events:none}.sz-opt.on{border-color:#7a5cc9;background:#f1ecfb}.sz-opt.on .sz-radio{color:#7a5cc9}.sz-radio{color:#b6aee0}.sz-q.sz-missing{border-color:#e0876d}
    .sz-nav{display:flex;gap:10px}.sz-nav .sz-btn{margin-top:18px}
</style>
<script>
var szPage = 0;
var szPages = Array.prototype.slice.call(document.querySelectorAll('.sz-page'));
function szStart() {
    document.getElementById('sz-intro').hidden = true;
    document.getElementById('sz-quiz').hidden = false;
    szShow(0);
}
function szAnswered(q) {
    return Array.prototype.some.call(q.querySelectorAll('input'), function (i) { return i.checked; });
}
function szProgress() {
    var qs = document.querySelectorAll('.sz-q');
    var done = Array.prototype.filter.call(qs, szAnswered).length;
    var pct = qs.length ? Math.round(done / qs.length * 100) : 0;
    document.getElementById('sz-pct').textContent = pct + '%';
    document.getElementById('sz-bar').style.width = pct + '%';
    document.getElementById('sz-qcount').textContent = done + ' of ' + qs.length + ' answered';
}
function szShow(index, quiet) {
    szPage = index;
    szPages.forEach(function (p, i) { p.hidden = i !== index; });
    document.getElementById('sz-prev').hidden = index === 0;
    var last = index >= szPages.length - 1;
    document.getElementById('sz-next').hidden = last;
    document.getElementById('sz-submit').hidden = !last;
    szProgress();
    if (!quiet) window.scrollTo({ top: document.getElementById("sz-quiz").getBoundingClientRect().top + window.scrollY - 80, behavior: "smooth" });
}
function szGo(delta) {
    if (delta > 0) {
        // Required questions on this page must be answered before moving on,
        // exactly as the app enforces.
        var missing = Array.prototype.filter.call(szPages[szPage].querySelectorAll('.sz-q[data-required="1"]'), function (q) { return !szAnswered(q); });
        szPages[szPage].querySelectorAll('.sz-q').forEach(function (q) { q.classList.remove('sz-missing'); });
        if (missing.length) { missing.forEach(function (q) { q.classList.add('sz-missing'); }); missing[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); return; }
    }
    var next = Math.max(0, Math.min(szPages.length - 1, szPage + delta));
    szShow(next);
}
document.querySelectorAll('.sz-opt').forEach(function (label) {
    label.addEventListener('change', function () {
        var q = label.closest('.sz-q');
        var input = label.querySelector('input');
        if (input.type === 'checkbox') {
            var max = Number(q.dataset.max) || 99;
            var checked = q.querySelectorAll('input:checked').length;
            if (checked > max) { input.checked = false; alert('You can choose up to ' + max + '.'); }
        }
        q.querySelectorAll('.sz-opt').forEach(function (l) { l.classList.toggle('on', l.querySelector('input').checked); });
        q.classList.remove('sz-missing');
        szProgress();
    });
});
document.getElementById('sz-form').addEventListener('submit', function (event) {
    var missing = Array.prototype.filter.call(document.querySelectorAll('.sz-q[data-required="1"]'), function (q) { return !szAnswered(q); });
    if (missing.length) {
        event.preventDefault();
        var page = szPages.findIndex(function (p) { return p.contains(missing[0]); });
        szShow(Math.max(0, page));
        missing.forEach(function (q) { q.classList.add('sz-missing'); });
    }
});
if (szPages.length) {
    var showingQuiz = !document.getElementById('sz-quiz').hidden;
    szShow(showingQuiz ? szPages.length - 1 : 0, true);
    if (!showingQuiz) { document.getElementById('sz-quiz').hidden = true; }
}
</script>
@endsection
