@extends('layouts.admin')
@section('title', 'Review & publish · '.$questionnaire->title)
@section('body')
@php($summary = $review['summary'])
@php($issueCount = count($review['issues']))
@php($warningCount = count($review['warnings']))
@php($creationFlow = (int) session('admin_questionnaire_creation_id') === $questionnaire->id && $questionnaire->status === 'draft')
<main class="content stack{{ $creationFlow ? ' questionnaire-creation-step' : '' }}">
    <a class="backlink" href="{{ $creationFlow ? route('admin.questionnaires.scoring', $questionnaire) : route('admin.questionnaires.sections.index', $questionnaire) }}">← Back to {{ $creationFlow ? 'Scoring' : $questionnaire->title }}</a>

    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif

    <div>
        <div class="eyebrow">{{ $creationFlow ? 'Step 4 of 4 · ' : '' }}Review &amp; publish</div>
        <h1 style="margin:2px 0 4px">{{ $questionnaire->title }}
            <span class="badge {{ $questionnaire->is_active && ! $questionnaire->isScheduled() ? 'active' : '' }}" style="vertical-align:middle;font-family:system-ui,sans-serif">{{ $questionnaire->publishState() }}</span>
        </h1>
        <p class="lede">The whole questionnaire has been checked — every section, question, answer and score, the result scale, every range and the support linked to it.</p>
    </div>

    @if($creationFlow)
        @include('admin.questionnaires._creation_progress', ['questionnaire' => $questionnaire, 'step' => 4])
    @else
        @include('admin.questionnaires._wizard', ['questionnaire' => $questionnaire, 'review' => $review, 'step' => 3])
    @endif

    {{-- ============ CHECKS ============ --}}
    <section class="panel">
        <div class="panel-head"><h2 style="margin:0">Review questionnaire</h2></div>
        <ul class="health-list review-list">
            @foreach($review['checks'] as $check)
                <li class="{{ $check['ok'] ? 'ok' : 'bad' }}"><span class="mark">{{ $check['ok'] ? '✓' : '✗' }}</span><span>{{ $check['label'] }}</span></li>
            @endforeach
        </ul>
    </section>

    {{-- ============ ISSUES ============ --}}
    @if($issueCount)
        <section class="panel" style="border-color:#f0c9c2">
            <div class="panel-head">
                <h2 style="margin:0 0 4px">⚠ {{ $issueCount }} {{ $issueCount === 1 ? 'thing needs' : 'things need' }} your attention</h2>
                <p class="lede">Fix these and the questionnaire can be published. Nothing reaches students until then.</p>
            </div>
            <ol class="attention-list">
                @foreach($review['issues'] as $issue)
                    @php($fixUrl = $creationFlow && in_array($issue['where'], ['Result scale', 'Result ranges'], true) ? route('admin.questionnaires.scoring', $questionnaire).'#ranges' : $issue['fix'])
                    <li>
                        <div class="grow">
                            <div class="item-title">{{ $issue['where'] }}</div>
                            <div>{{ $issue['what'] }}</div>
                        </div>
                        <a class="button button-secondary" href="{{ $fixUrl }}">Fix</a>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- ============ WORTH A LOOK ============ --}}
    @if($warningCount)
        <section class="panel" style="border-color:#e8d9a8;background:#fffdf5">
            <div class="panel-head">
                <h2 style="margin:0 0 4px">{{ $warningCount }} {{ $warningCount === 1 ? 'thing' : 'things' }} worth a look</h2>
                <p class="lede">These don't stop you publishing. Check they're what you intend — nothing has been changed automatically.</p>
            </div>
            <ol class="attention-list">
                @foreach($review['warnings'] as $warning)
                    @php($reviewUrl = $creationFlow && $warning['where'] === 'Result ranges' ? route('admin.questionnaires.scoring', $questionnaire).'#ranges' : $warning['fix'])
                    <li>
                        <div class="grow">
                            <div class="item-title">{{ $warning['where'] }}</div>
                            <div>{{ $warning['what'] }}</div>
                            @if($warning['detail'])<div class="muted" style="margin-top:3px;font-size:.88rem">{{ $warning['detail'] }}</div>@endif
                        </div>
                        <a class="button button-secondary" href="{{ $reviewUrl }}">Review</a>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- ============ PUBLISH ============ --}}
    <section class="panel {{ $review['ready'] ? 'publish-panel' : '' }}">
        @if($review['ready'])
            <div class="split" style="align-items:center">
                <div>
                    <h2 style="margin:0 0 2px">Everything looks good.</h2>
                    <p class="lede">
                        @if($questionnaire->isScheduled())
                            Published and <strong>scheduled to open on {{ $questionnaire->published_at->format('j F Y \a\t g:ia') }}</strong>. Students can't see it until then — to open it now, clear the go-live date in <a href="{{ route('admin.questionnaires.details', $questionnaire) }}">Details</a> and publish again.
                        @elseif($questionnaire->is_active)
                            This questionnaire is already published. Publish again to send your latest changes to students.
                        @elseif($questionnaire->published_at?->isFuture())
                            Ready to publish. A go-live date is set, so students will see it from <strong>{{ $questionnaire->published_at->format('j F Y \a\t g:ia') }}</strong> — clear it in <a href="{{ route('admin.questionnaires.details', $questionnaire) }}">Details</a> if you want it available straight away.
                        @else
                            Ready to publish whenever you are.
                        @endif
                    </p>
                </div>
                <button type="button" class="button" data-toggle="confirm-publish">{{ $questionnaire->is_active ? 'Republish questionnaire' : 'Publish questionnaire' }}</button>
            </div>

            <div id="confirm-publish" hidden style="margin-top:18px;padding-top:18px;border-top:1px solid #b8dcd4">
                <h3 style="margin:0 0 10px">Ready to publish?</h3>
                <p class="item-title" style="margin:0 0 8px">{{ $questionnaire->title }}</p>
                <ul class="health-list" style="margin-bottom:12px">
                    <li><span class="mark">·</span>{{ $summary['sections'] }} {{ \Illuminate\Support\Str::plural('section', $summary['sections']) }}</li>
                    <li><span class="mark">·</span>{{ $summary['questions'] }} {{ \Illuminate\Support\Str::plural('question', $summary['questions']) }}</li>
                    @if($summary['scale'])<li><span class="mark">·</span>Result scale: {{ $summary['scale'][0] }}–{{ $summary['scale'][1] }}</li>@endif
                    <li><span class="mark">·</span>{{ $summary['ranges'] }} result {{ \Illuminate\Support\Str::plural('range', $summary['ranges']) }}</li>
                    <li><span class="mark">·</span>{{ $summary['interventions'] }} {{ \Illuminate\Support\Str::plural('intervention', $summary['interventions']) }}</li>
                </ul>
                <p class="lede" style="margin-bottom:14px">
                    @if($questionnaire->published_at?->isFuture())
                        Once published, this questionnaire opens to students on <strong>{{ $questionnaire->published_at->format('j F Y \a\t g:ia') }}</strong>. Any other version becomes a draft.
                    @else
                        Once published, this questionnaire is the one students see. Any other version becomes a draft.
                    @endif
                </p>
                <form method="POST" action="{{ route('admin.questionnaires.publish', $questionnaire) }}" class="actions">@csrf @method('PATCH')
                    <button type="button" class="button button-secondary" data-toggle="confirm-publish">Cancel</button>
                    <button class="button" type="submit">Publish</button>
                </form>
            </div>
        @else
            <div class="split" style="align-items:center">
                <div>
                    <h2 style="margin:0 0 2px">Not ready to publish yet</h2>
                    <p class="lede">Please fix the {{ $issueCount }} {{ $issueCount === 1 ? 'item' : 'items' }} above before publishing. Your work is safe as a draft.</p>
                </div>
                <button type="button" class="button" disabled title="Fix the items above first">Publish questionnaire</button>
            </div>
        @endif
    </section>
</main>

<script>
document.querySelectorAll('[data-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
        var target = document.getElementById(button.getAttribute('data-toggle'));
        if (target) target.hidden = !target.hidden;
    });
});
</script>
@endsection
