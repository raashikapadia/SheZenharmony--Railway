@extends('layouts.admin')
@section('title', 'Edit · '.$questionnaire->title)
@section('body')
@php($bands = old('bands', $questionnaire->scoreBands->count()
        ? $questionnaire->scoreBands->map(fn ($b) => $b->only(['id','scope','code','label','min_score','max_score','position','is_active']))->all()
        : [
            ['scope'=>'overall','code'=>'low','label'=>'Low mental well-being','min_score'=>0,'max_score'=>20,'position'=>1,'is_active'=>true],
            ['scope'=>'overall','code'=>'moderate','label'=>'Moderate mental well-being','min_score'=>21,'max_score'=>30,'position'=>2,'is_active'=>true],
            ['scope'=>'overall','code'=>'high','label'=>'High mental well-being','min_score'=>31,'max_score'=>40,'position'=>3,'is_active'=>true],
        ]))
<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaire Management</a>

    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <div>
        <h1 style="margin:0 0 4px">{{ $questionnaire->title }} <span class="muted" style="font-size:1rem;font-family:inherit">v{{ $questionnaire->version }} · {{ ucfirst($questionnaire->status) }}{{ $questionnaire->is_active ? ' · live' : '' }}</span></h1>
        <p class="lede">Everything about this questionnaire is edited here — details, sections, questions and result ranges. Submissions are scored automatically.</p>
    </div>

    @if($showWizard)
        @include('admin.questionnaires._wizard', [
            'step' => (! $publishError && $sectionCount) ? 3 : 2,
            'sectionsHint' => $sectionCount
                ? $sectionCount.' section'.($sectionCount === 1 ? '' : 's').' · '.$questionCount.' question'.($questionCount === 1 ? '' : 's')
                : 'none yet',
        ])
    @endif

    {{-- Publish decision: live, blocked, or ready --}}
    @if($questionnaire->is_active)
        <div class="status">✓ This version is <strong>live for students</strong>. Changes here don't affect them until you publish again.</div>
    @elseif($publishError)
        <div class="errors">
            <strong>Not ready to publish yet:</strong> {{ $publishError }}<br>
            <span class="muted">Fix this below. Your work is safe as a draft — nothing is shown to students.</span>
        </div>
    @else
        <section class="panel publish-panel">
            <div class="split" style="align-items:flex-start">
                <div>
                    <h2 style="margin:0 0 2px">Ready when you are</h2>
                    <p class="lede">Everything checks out. Publish now to make this the live questionnaire for students — every other version becomes a draft. Or keep working; it stays a private draft until you publish.</p>
                </div>
                <div class="actions">
                    <form method="POST" action="{{ route('admin.questionnaires.publish', $questionnaire) }}">@csrf @method('PATCH')<button class="button" type="submit">Publish now</button></form>
                    <a class="button button-secondary" href="{{ route('admin.questionnaires.index') }}">Keep as draft</a>
                </div>
            </div>
            @if($questionnaire->published_at)
                <p class="muted" style="margin:12px 0 0;font-size:.85rem">Planned go-live: {{ $questionnaire->published_at->format('D j M Y, H:i') }} (set in Details below).</p>
            @endif
        </section>
    @endif

    {{-- 1. Details --}}
    <section class="panel">
        <div class="panel-head"><h2>1. Details</h2></div>
        <form class="stack-sm" method="POST" action="{{ route('admin.questionnaires.details', $questionnaire) }}">@csrf @method('PATCH')
            <div><label>Title</label><input name="title" value="{{ old('title', $questionnaire->title) }}" required></div>
            <div><label>Description <span class="muted">(shown to students on the intro screen)</span></label><textarea name="description">{{ old('description', $questionnaire->description) }}</textarea></div>
            <div class="field-row">
                <div><label>Period / label</label><input name="period" value="{{ old('period', $questionnaire->period) }}" placeholder="e.g. Semester 1"></div>
                <div><label>Version</label><input name="version" type="number" min="1" value="{{ old('version', $questionnaire->version) }}" required></div>
                <div><label>Status</label><select name="status">
                    @foreach(['draft'=>'Draft','published'=>'Published (live for students)','archived'=>'Archived'] as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('status', $questionnaire->status) === $val)>{{ $lbl }}</option>
                    @endforeach
                </select></div>
            </div>
            <label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $questionnaire->is_active))> Active for students</label>
            <div><label for="published_at">Go live at <span class="muted">(optional — leave blank to publish immediately)</span></label>
            <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at', optional($questionnaire->published_at)->format('Y-m-d\TH:i')) }}"></div>
            <div class="actions">
                <button class="button" type="submit">Save details</button>
                <a class="button button-secondary" href="{{ route('admin.questionnaires.scoring', $questionnaire) }}">Scoring overview</a>
                <a class="button button-link" href="{{ route('admin.questionnaires.edit', $questionnaire) }}">Advanced editor (reuse question bank)</a>
            </div>
        </form>
    </section>

    {{-- 2. Sections & questions --}}
    <section class="panel">
        <div class="split panel-head">
            <div>
                <h2>2. Sections &amp; questions</h2>
                <p class="lede">Each section is a wellbeing category; its <strong>weight</strong> sets how much it counts toward the overall score. Total weight now: <strong>{{ rtrim(rtrim(number_format($sumWeights, 2), '0'), '.') }}</strong>.</p>
            </div>
            <a class="button" href="{{ route('admin.questionnaires.sections.create', $questionnaire) }}"><span>＋</span>Add section</a>
        </div>
    </section>

    @forelse($questionnaire->sections->sortBy('position') as $section)
        @php($questions = ($questionsBySection[$section->id] ?? collect()))
        <section class="panel">
            <div class="split panel-head">
                <div>
                    <h3>{{ $section->position }}. {{ $section->title }} @unless($section->is_active)<span class="badge">archived</span>@endunless</h3>
                    <p class="lede">
                        Weight {{ rtrim(rtrim(number_format($section->category_weight, 2), '0'), '.') }} · {{ $questions->count() }} question{{ $questions->count() === 1 ? '' : 's' }}
                        @if($section->description) · {{ $section->description }} @endif
                    </p>
                </div>
                <div class="actions">
                    <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.edit', [$questionnaire, $section]) }}">Edit section</a>
                    <a class="button" href="{{ route('admin.questionnaires.sections.questions.create', [$questionnaire, $section]) }}"><span>＋</span>Add question</a>
                    <details class="more">
                        <summary class="button button-link">⋯</summary>
                        <div class="more-menu">
                            <form method="POST" action="{{ route('admin.questionnaires.sections.destroy', [$questionnaire, $section]) }}" onsubmit="return confirm('Delete or archive this section?')">@csrf @method('DELETE')<button type="submit" class="danger">Delete section…</button></form>
                        </div>
                    </details>
                </div>
            </div>

            @if($questions->isEmpty())
                <p class="lede">No questions in this section yet.</p>
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>#</th><th>Question</th><th>Options</th><th>Required</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($questions as $i => $question)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                <div class="item-title">{{ $question->question_text }}</div>
                                <span class="muted">{{ $question->options->where('is_active', true)->pluck('label')->join(' · ') }}</span>
                            </td>
                            <td>{{ $question->options->where('is_active', true)->count() }}</td>
                            <td>{{ $question->pivot->is_required ? 'Yes' : 'No' }}</td>
                            <td><span class="badge {{ $question->is_active ? 'active' : '' }}">{{ $question->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="actions">
                                <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.questions.edit', [$questionnaire, $section, $question]) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.questionnaires.sections.questions.destroy', [$questionnaire, $section, $question]) }}" onsubmit="return confirm('Remove this question from the section?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
    @empty
        <section class="panel" style="text-align:center;padding:40px 24px">
            <h3 style="margin:0 0 6px">Add your first section</h3>
            <p class="lede" style="margin:0 auto 16px">Sections are the wellbeing categories students are scored on (for example <em>Emotional</em>, <em>Academic</em>, <em>Social</em>). Add a section, then add its questions — each question can have any number of answer options with its own scores.</p>
            <a class="button" href="{{ route('admin.questionnaires.sections.create', $questionnaire) }}"><span>＋</span>Add section</a>
        </section>
    @endforelse

    @php($unsectioned = ($questionsBySection[null] ?? ($questionsBySection[''] ?? collect())))
    @if($unsectioned->isNotEmpty())
        <section class="panel">
            <div class="panel-head"><h3>Not in a section ({{ $unsectioned->count() }})</h3>
            <p class="lede">Attached to the questionnaire but not in any section, so weighted scoring ignores them. Assign them in the Advanced editor.</p></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Question</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($unsectioned as $question)
                    <tr><td>{{ $question->question_text }}</td><td><span class="badge {{ $question->is_active ? 'active' : '' }}">{{ $question->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                @endforeach
                </tbody>
            </table></div>
        </section>
    @endif

    {{-- 3. Result ranges --}}
    <section class="panel">
        <div class="panel-head">
            <h2>3. Result ranges</h2>
            <p class="lede"><strong>Overall</strong> ranges label the wellbeing score (0–{{ (int) ceil($sumWeights) }} for the current weights). <strong>Stress</strong> ranges (0–100) label the optional stress indicator. Ranges of the same type must not overlap and must leave no gap.</p>
        </div>
        <form method="POST" action="{{ route('admin.questionnaires.ranges', $questionnaire) }}">@csrf @method('PATCH')
            <div id="band-rows">
            @foreach($bands as $index => $band)
                <div class="editor-row band-row">
                    @if(isset($band['id']))<input type="hidden" name="bands[{{ $index }}][id]" value="{{ $band['id'] }}">@endif
                    <div class="fld"><label>Type</label><select name="bands[{{ $index }}][scope]">
                        <option value="overall" @selected(($band['scope'] ?? 'overall') === 'overall')>Overall wellbeing</option>
                        <option value="stress" @selected(($band['scope'] ?? 'overall') === 'stress')>Stress</option>
                    </select></div>
                    <div class="fld"><label>Code</label><input name="bands[{{ $index }}][code]" value="{{ $band['code'] }}" required></div>
                    <div class="fld grow"><label>Label</label><input name="bands[{{ $index }}][label]" value="{{ $band['label'] }}" required></div>
                    <div class="fld narrow"><label>Min</label><input type="number" name="bands[{{ $index }}][min_score]" value="{{ $band['min_score'] }}" required></div>
                    <div class="fld narrow"><label>Max</label><input type="number" name="bands[{{ $index }}][max_score]" value="{{ $band['max_score'] }}" required></div>
                    <div class="fld narrow"><label>Order</label><input type="number" min="0" name="bands[{{ $index }}][position]" value="{{ $band['position'] }}" required></div>
                    <label class="remember"><input type="checkbox" name="bands[{{ $index }}][is_active]" value="1" @checked($band['is_active'] ?? false)> Active</label>
                    <button type="button" class="button button-secondary row-remove band-remove">Remove</button>
                </div>
            @endforeach
            </div>
            <button type="button" id="add-band" class="button button-secondary add-row">＋ Add result range</button>
            <div class="actions" style="margin-top:16px"><button class="button" type="submit">Save result ranges</button></div>
        </form>
    </section>
</main>

<script>
(function () {
    var rows = document.getElementById('band-rows');
    var addBtn = document.getElementById('add-band');
    var nextIndex = {{ count($bands) }};
    function wire(row) {
        var r = row.querySelector('.band-remove');
        if (r) r.addEventListener('click', function () { row.remove(); });
    }
    rows.querySelectorAll('.band-row').forEach(wire);
    addBtn.addEventListener('click', function () {
        var i = nextIndex++;
        var pos = rows.querySelectorAll('.band-row').length + 1;
        var div = document.createElement('div');
        div.className = 'editor-row band-row';
        div.innerHTML =
            '<div class="fld"><label>Type</label><select name="bands[' + i + '][scope]"><option value="overall">Overall wellbeing</option><option value="stress">Stress</option></select></div>' +
            '<div class="fld"><label>Code</label><input name="bands[' + i + '][code]" required></div>' +
            '<div class="fld grow"><label>Label</label><input name="bands[' + i + '][label]" required></div>' +
            '<div class="fld narrow"><label>Min</label><input type="number" name="bands[' + i + '][min_score]" required></div>' +
            '<div class="fld narrow"><label>Max</label><input type="number" name="bands[' + i + '][max_score]" required></div>' +
            '<div class="fld narrow"><label>Order</label><input type="number" min="0" name="bands[' + i + '][position]" value="' + pos + '" required></div>' +
            '<label class="remember"><input type="checkbox" name="bands[' + i + '][is_active]" value="1" checked> Active</label>' +
            '<button type="button" class="button button-secondary row-remove band-remove">Remove</button>';
        rows.appendChild(div);
        wire(div);
    });
})();
</script>
@endsection
