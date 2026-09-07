@extends('layouts.admin')
@section('title', 'Preview · '.$questionnaire->title)
@section('body')
@php($ordered = collect())
@php($sorted = $questionnaire->sections->sortBy('position'))
@foreach($sorted as $section)
    @foreach(($questionsBySection[$section->id] ?? collect()) as $q)
        @php($ordered->push($q))
    @endforeach
@endforeach
@foreach(($questionsBySection[null] ?? ($questionsBySection[''] ?? collect())) as $q)
    @php($ordered->push($q))
@endforeach
@php($total = $ordered->count())

<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaire Management</a>
    <div class="split" style="align-items:flex-start">
        <div>
            <h1 style="margin:0 0 4px">Preview</h1>
            <p class="lede">This is <strong>{{ $questionnaire->title }}</strong> (v{{ $questionnaire->version }}, {{ $questionnaire->status }}) shown exactly as it appears in the SheZen app. Page through it below — no result is saved.</p>
        </div>
        <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">Edit</a>
    </div>

    <div class="sz-frame">
        <div class="sz-notch"></div>
        <div class="sz-screen">
            <div class="sz-appbar">Your stress check</div>

            {{-- Intro --}}
            <div class="sz-view" id="sz-intro">
                <div class="sz-avatar">♥</div>
                <h2 class="sz-h">Take a moment to check in</h2>
                <p class="sz-sub">{{ $questionnaire->description ?: 'Choose the response that feels most true for you right now.' }}</p>
                <div class="sz-card sz-intro-card">
                    <div class="sz-row"><span class="sz-ic">≣</span><span>{{ $total }} question{{ $total === 1 ? '' : 's' }} from the active SheZen questionnaire</span></div>
                    <div class="sz-row"><span class="sz-ic">🔒</span><span>Answers are saved with your SheZen ID, not shown with your university email</span></div>
                    <div class="sz-row"><span class="sz-ic">＋</span><span>Your result supports reflection and is not a medical diagnosis</span></div>
                </div>
                <button class="sz-btn" type="button" onclick="szStart()" @disabled($total === 0)>Begin stress check →</button>
                @if($total === 0)<p class="sz-sub" style="margin-top:12px">Add sections and questions before this can be taken.</p>@endif
            </div>

            {{-- Questions (one at a time) --}}
            <div class="sz-view" id="sz-quiz" hidden>
                <div class="sz-qhead">
                    <span class="sz-qcount" id="sz-qcount">Question 1 of {{ $total }}</span>
                    <span class="sz-qpct" id="sz-pct">0%</span>
                </div>
                <div class="sz-progress"><span id="sz-bar" style="width:0"></span></div>

                @foreach($ordered as $i => $question)
                    <div class="sz-qwrap" data-idx="{{ $i }}" @if($i > 0) hidden @endif>
                        <div class="sz-card">
                            <p class="sz-qtext">{{ $question->question_text }}</p>
                            <div class="sz-opts">
                                @forelse($question->options as $option)
                                    <button type="button" class="sz-opt" onclick="szPick(this)">
                                        <span class="sz-radio">○</span>
                                        <span>{{ $option->label }}</span>
                                    </button>
                                @empty
                                    <p class="sz-sub">This question has no answer options yet.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="sz-nav">
                    <button type="button" class="sz-btn sz-btn-ghost" id="sz-prev" onclick="szGo(-1)">Previous</button>
                    <button type="button" class="sz-btn" id="sz-next" onclick="szGo(1)">Next</button>
                </div>
                <p class="sz-sub" style="text-align:center;margin-top:12px">Preview only — no result is saved.</p>
            </div>
        </div>
    </div>
</main>

<style>
    .sz-frame{width:min(420px,100%);margin:0 auto;background:#141019;border-radius:44px;padding:12px;box-shadow:0 30px 70px rgba(20,16,25,.28)}
    .sz-notch{width:130px;height:26px;background:#141019;border-radius:0 0 16px 16px;margin:0 auto -6px;position:relative;z-index:2}
    .sz-screen{background:#FBF8FC;border-radius:34px;overflow:hidden;min-height:640px;display:flex;flex-direction:column;font-family:"Segoe UI",Inter,system-ui,sans-serif;color:#2E2731}
    .sz-appbar{padding:22px 22px 8px;font-size:1.15rem;font-weight:800;letter-spacing:-.2px}
    .sz-view{padding:14px 22px 26px;flex:1}
    #sz-intro{text-align:center;display:flex;flex-direction:column;align-items:center}
    .sz-avatar{width:76px;height:76px;border-radius:50%;background:#F1EAF4;color:#76517B;display:grid;place-items:center;font-size:1.9rem;margin:14px 0 20px}
    .sz-h{font-size:1.4rem;font-weight:800;letter-spacing:-.4px;margin:0 0 8px}
    .sz-sub{color:#716775;margin:0;line-height:1.5}
    .sz-card{background:#FFFCFF;border:1px solid #E5DDE7;border-radius:22px;padding:20px;text-align:left;width:100%}
    .sz-intro-card{margin:22px 0 26px;display:flex;flex-direction:column;gap:14px}
    .sz-row{display:flex;gap:12px;align-items:flex-start;line-height:1.45}
    .sz-ic{flex:0 0 auto;width:22px;text-align:center;color:#76517B}
    .sz-btn{appearance:none;border:0;cursor:pointer;width:100%;min-height:52px;padding:14px 20px;border-radius:16px;background:#76517B;color:#fff;font-size:.95rem;font-weight:700}
    .sz-btn:disabled{opacity:.45;cursor:not-allowed}
    .sz-btn-ghost{background:transparent;color:#2E2731;border:1px solid #E5DDE7}
    .sz-qhead{display:flex;justify-content:space-between;align-items:center;margin:6px 0 10px}
    .sz-qcount{color:#76517B;font-weight:700}
    .sz-qpct{color:#716775;font-size:.9rem}
    .sz-progress{height:8px;border-radius:8px;background:#F1EAF4;overflow:hidden;margin-bottom:26px}
    .sz-progress>span{display:block;height:8px;border-radius:8px;background:#76517B;transition:width .2s ease}
    .sz-qtext{font-size:1.2rem;font-weight:700;line-height:1.3;margin:0 0 20px}
    .sz-opts{display:flex;flex-direction:column;gap:10px}
    .sz-opt{appearance:none;cursor:pointer;text-align:left;display:flex;gap:12px;align-items:center;padding:15px 16px;border:1px solid #EDE6EF;border-radius:16px;background:#FFFCFF;color:#2E2731;font:inherit}
    .sz-opt:hover{border-color:#cdbfd6}
    .sz-opt.is-sel{border-color:#76517B;border-width:1.5px;background:#F1EAF4;font-weight:600}
    .sz-radio{flex:0 0 auto;color:#B8A9C0;font-size:1.1rem}
    .sz-opt.is-sel .sz-radio{color:#76517B}
    .sz-nav{display:flex;gap:12px;margin-top:24px}
    .sz-nav .sz-btn{flex:1}
    #sz-prev[hidden]{display:none}
</style>
<script>
(function () {
    var total = {{ $total }};
    var idx = 0;
    var wraps = [].slice.call(document.querySelectorAll('.sz-qwrap'));
    var intro = document.getElementById('sz-intro');
    var quiz = document.getElementById('sz-quiz');
    var bar = document.getElementById('sz-bar');
    var pct = document.getElementById('sz-pct');
    var count = document.getElementById('sz-qcount');
    var prev = document.getElementById('sz-prev');
    var next = document.getElementById('sz-next');

    window.szStart = function () {
        if (!total) return;
        intro.hidden = true;
        quiz.hidden = false;
        render();
    };
    window.szGo = function (delta) {
        var t = idx + delta;
        if (t < 0 || t >= total) return;
        idx = t;
        render();
    };
    window.szPick = function (btn) {
        var group = btn.closest('.sz-opts');
        [].forEach.call(group.querySelectorAll('.sz-opt'), function (o) {
            o.classList.remove('is-sel');
            o.querySelector('.sz-radio').textContent = '○';
        });
        btn.classList.add('is-sel');
        btn.querySelector('.sz-radio').textContent = '●';
    };

    function render() {
        wraps.forEach(function (w) { w.hidden = (+w.dataset.idx !== idx); });
        var done = idx + 1;
        count.textContent = 'Question ' + done + ' of ' + total;
        var p = Math.round(done / total * 100);
        bar.style.width = p + '%';
        pct.textContent = p + '%';
        prev.hidden = idx === 0;
        next.textContent = idx === total - 1 ? 'Submit check-in' : 'Next';
    }
})();
</script>
@endsection
