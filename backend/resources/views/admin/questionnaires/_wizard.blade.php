{{-- The three screens of building a questionnaire, as navigation.
     Pass `questionnaire` (and the `review` from QuestionnaireReview) to make
     the steps links and mark each done or needing attention; without a
     questionnaire (the create page) it is a plain progress strip. --}}
@php($step = $step ?? 1)
@php($questionnaire = $questionnaire ?? null)
@php($review = $review ?? null)
@php($issuesFor = function (array $places) use ($review): int {
    if (! $review) return 0;
    return collect($review['issues'])->filter(fn ($i) => collect($places)->contains(fn ($p) => str_starts_with($i['where'], $p)))->count();
})
@php($steps = [
    1 => ['label' => 'Details', 'route' => $questionnaire ? route('admin.questionnaires.details', $questionnaire) : null,
          'issues' => $issuesFor(['Questionnaire details', 'Result scale', 'Result ranges', 'Scoring'])],
    2 => ['label' => 'Sections & questions', 'route' => $questionnaire ? route('admin.questionnaires.sections.index', $questionnaire) : null,
          'issues' => $issuesFor(['Sections', 'Section "', 'Questions'])],
    3 => ['label' => 'Review & publish', 'route' => $questionnaire ? route('admin.questionnaires.review', $questionnaire) : null,
          'issues' => 0],
])
<ol class="wizard-steps" aria-label="Questionnaire setup progress">
    @foreach($steps as $n => $s)
        @php($done = $review && $n < 3 && $s['issues'] === 0 && ($n !== 2 || $review['summary']['questions'] > 0))
        @php($attention = $review && $s['issues'] > 0)
        @php($class = $n === $step ? 'current' : ($attention ? 'attention' : ($done ? 'done' : '')))
        <li class="{{ $class }}">
            @if($s['route'])<a href="{{ $s['route'] }}">@endif
                <span class="n">{{ $attention ? '!' : ($done && $n !== $step ? '✓' : $n) }}</span> {{ $s['label'] }}
                @if($attention)<span class="wizard-hint">— {{ $s['issues'] }} to fix</span>@endif
            @if($s['route'])</a>@endif
        </li>
    @endforeach
</ol>
