@extends('layouts.admin')
@section('title', 'Edit · '.$questionnaire->title)
@section('body')
@php($presets = \App\Support\AnswerScalePresets::all())
@php($typeLabels = collect(\App\Enums\QuestionType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
@php($orderedSections = $questionnaire->sections->sortBy([['position', 'asc'], ['id', 'asc']])->values())
@php($unsectioned = ($questionsBySection[null] ?? ($questionsBySection[''] ?? collect())))
<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaire Management</a>

    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    {{-- ============ QUESTIONNAIRE ============ --}}
    <section class="panel">
        <div class="split" style="align-items:flex-start">
            <div style="min-width:0">
                <div class="eyebrow">Step 2 of 3 · Sections &amp; questions</div>
                <h1 style="margin:2px 0 4px;overflow-wrap:anywhere">{{ $questionnaire->title }}
                    <span class="badge {{ $questionnaire->is_active && ! $questionnaire->isScheduled() ? 'active' : '' }}" style="vertical-align:middle;font-family:system-ui,sans-serif">{{ $questionnaire->publishState() }}</span>
                </h1>
                @if($questionnaire->description)
                    <p class="lede" style="max-width:70ch">{{ $questionnaire->description }}</p>
                @else
                    <p class="lede muted">No description yet — students see it on the intro screen.</p>
                @endif
                <p class="muted" style="margin:8px 0 0;font-size:.88rem">{{ $sectionCount }} {{ \Illuminate\Support\Str::plural('section', $sectionCount) }} · {{ $questionCount }} {{ \Illuminate\Support\Str::plural('question', $questionCount) }}</p>
            </div>
            <div class="actions">
                <a class="button button-secondary" href="{{ route('admin.questionnaires.details', $questionnaire) }}">Edit details</a>
                <a class="button" href="{{ route('admin.questionnaires.sections.create', $questionnaire) }}"><span>＋</span>Add section</a>
            </div>
        </div>

    </section>

    @include('admin.questionnaires._wizard', ['questionnaire' => $questionnaire, 'review' => $review, 'step' => 2])

    {{-- ============ SECTIONS ============ --}}
    @if($orderedSections->isEmpty())
        <section class="panel empty-state">
            <h2 style="margin:0 0 6px">No sections yet</h2>
            <p class="lede" style="margin:0 auto 18px">Create your first section to start building your questionnaire.</p>
            <a class="button" href="{{ route('admin.questionnaires.sections.create', $questionnaire) }}"><span>＋</span>Add section</a>
        </section>
    @endif

    @foreach($orderedSections as $sectionIndex => $section)
        @php($questions = ($questionsBySection[$section->id] ?? collect())->values())
        @php($count = $questions->count())
        <section class="panel section-panel" id="section-{{ $section->id }}">
            <div class="split" style="align-items:flex-start">
                <div class="section-head">
                    <div class="reorder">
                        <form method="POST" action="{{ route('admin.questionnaires.sections.move', [$questionnaire, $section]) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="up"><button type="submit" class="arrow" title="Move section up" aria-label="Move section up" @disabled($sectionIndex === 0)>▲</button></form>
                        <form method="POST" action="{{ route('admin.questionnaires.sections.move', [$questionnaire, $section]) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="down"><button type="submit" class="arrow" title="Move section down" aria-label="Move section down" @disabled($sectionIndex === $orderedSections->count() - 1)>▼</button></form>
                    </div>
                    <div style="min-width:0">
                        <div class="eyebrow">Section {{ $sectionIndex + 1 }} @unless($section->is_active)<span class="badge" style="margin-left:6px">hidden from students</span>@endunless</div>
                        <h2 style="margin:2px 0 0;overflow-wrap:anywhere">{{ $section->title }}</h2>
                        @if($section->description)<p class="lede" style="margin-top:4px">{{ $section->description }}</p>@endif
                    </div>
                </div>
                <div class="actions">
                    <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.edit', [$questionnaire, $section]) }}">Edit section</a>
                    <form method="POST" action="{{ route('admin.questionnaires.sections.destroy', [$questionnaire, $section]) }}" data-confirm="Delete this section?&#10;&#10;“{{ $section->title }}”{{ $count ? ' and the '.$count.' '.\Illuminate\Support\Str::plural('question', $count).' inside it' : '' }} will be removed from the questionnaire. This cannot be undone.">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                </div>
            </div>

            <div class="questions-label">Questions <span class="muted">({{ $count }})</span></div>

            @if($questions->isEmpty())
                <div class="empty-state small">
                    <p class="item-title" style="margin:0 0 2px">No questions in this section yet.</p>
                    <p class="lede" style="margin:0 auto 14px">Add a question to get started.</p>
                    <a class="button" href="{{ route('admin.questionnaires.sections.questions.create', [$questionnaire, $section]) }}"><span>＋</span>Add question</a>
                </div>
            @else
                <div class="question-list">
                @foreach($questions as $i => $question)
                    @php($options = $question->options->where('is_active', true)->sortBy('position')->values())
                    <article class="question-card">
                        <div class="reorder">
                            <form method="POST" action="{{ route('admin.questionnaires.sections.questions.move', [$questionnaire, $section, $question]) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="up"><button type="submit" class="arrow" title="Move up" aria-label="Move question up" @disabled($i === 0)>▲</button></form>
                            <form method="POST" action="{{ route('admin.questionnaires.sections.questions.move', [$questionnaire, $section, $question]) }}">@csrf @method('PATCH')<input type="hidden" name="direction" value="down"><button type="submit" class="arrow" title="Move down" aria-label="Move question down" @disabled($i === $count - 1)>▼</button></form>
                        </div>
                        <div class="question-body">
                            <div class="item-title question-text">{{ $i + 1 }}. {{ $question->question_text }}</div>
                            <div class="question-meta">
                                {{ $typeLabels[$question->question_type] ?? ucfirst($question->question_type) }}
                                @unless($question->pivot->is_required) · optional @endunless
                                @unless($question->is_active) · <span class="badge">inactive</span> @endunless
                            </div>
                            <ul class="answer-list" aria-label="Answer options">
                                @foreach($options as $option)
                                    <li><span class="answer-dot"></span>{{ $option->label }}@if($option->score !== null) <span class="muted">({{ $option->score }})</span>@endif</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="question-actions">
                            <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.questions.edit', [$questionnaire, $section, $question]) }}">Edit</a>
                            <form method="POST" action="{{ route('admin.questionnaires.sections.questions.destroy', [$questionnaire, $section, $question]) }}" data-confirm="Delete this question?&#10;&#10;Are you sure you want to delete this question? This action cannot be undone.">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                        </div>
                    </article>
                @endforeach
                </div>

                <div class="actions" style="margin-top:14px;align-items:center">
                    <a class="button" href="{{ route('admin.questionnaires.sections.questions.create', [$questionnaire, $section]) }}"><span>＋</span>Add question</a>
                    <button type="button" class="button button-link" data-toggle="bulk-{{ $section->id }}">or paste several at once</button>
                </div>
            @endif

            {{-- Several questions in one go — the fast way to build a long
                 section. Hidden behind a link so the page stays simple. --}}
            <form id="bulk-{{ $section->id }}" class="stack-sm bulk-add" method="POST" action="{{ route('admin.questionnaires.sections.questions.bulk', [$questionnaire, $section]) }}" @unless(old('_section_id') == $section->id) hidden @endunless>@csrf
                <input type="hidden" name="_section_id" value="{{ $section->id }}">
                <div><label>Questions <span class="muted">— one per line</span></label>
                <textarea name="questions_text" rows="4" placeholder="I often feel overwhelmed by my workload.&#10;I find it hard to relax in my free time.">{{ old('_section_id') == $section->id ? old('questions_text') : '' }}</textarea></div>
                <div class="field-row" style="align-items:flex-end">
                    <div><label>Answer options for all of them</label><select name="scale">
                        @foreach($presets as $key => $preset)
                            <option value="{{ $key }}" @selected((old('_section_id') == $section->id ? old('scale') : \App\Support\AnswerScalePresets::DEFAULT) === $key)>{{ $preset['label'] }}</option>
                        @endforeach
                    </select></div>
                    <div class="actions">
                        <button class="button" type="submit">Add questions</button>
                        <button type="button" class="button button-secondary" data-toggle="bulk-{{ $section->id }}">Cancel</button>
                    </div>
                </div>
            </form>
        </section>
    @endforeach

    @if($unsectioned->isNotEmpty())
        <section class="panel">
            <div class="eyebrow">Needs a section</div>
            <h2 style="margin:2px 0 4px">{{ $unsectioned->count() }} {{ \Illuminate\Support\Str::plural('question', $unsectioned->count()) }} not in any section</h2>
            <p class="lede">Students only see questions inside a section. Edit each one and it will join the section you save it from — or delete it.</p>
            <ul class="answer-list" style="margin-top:10px">
                @foreach($unsectioned as $question)<li><span class="answer-dot"></span>{{ $question->question_text }}</li>@endforeach
            </ul>
        </section>
    @endif

    <div class="split" style="align-items:center">
        <a class="button button-secondary" href="{{ route('admin.questionnaires.details', $questionnaire) }}">← Details</a>
        <a class="button" href="{{ route('admin.questionnaires.review', $questionnaire) }}">Review &amp; publish →</a>
    </div>
</main>

<script>
(function () {
    // Show / hide panels (details form, bulk add) without leaving the page.
    document.querySelectorAll('[data-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-toggle'));
            if (!target) return;
            target.hidden = !target.hidden;
            if (!target.hidden) {
                var first = target.querySelector('input:not([type=hidden]), textarea');
                if (first) first.focus();
            }
        });
    });

    // One clear confirmation for every delete.
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!confirm(form.getAttribute('data-confirm'))) event.preventDefault();
        });
    });

})();
</script>
@endsection
