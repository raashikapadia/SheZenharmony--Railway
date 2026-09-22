{{-- The five screens of building a questionnaire, as navigation. Pass
     `questionnaire`, the current `step` (1–5) and, when available, the
     `review` from QuestionnaireReview so each step shows done / needs
     attention. Without a review it is a plain progress strip. --}}
@php($step = $step ?? 1)
@php($review = $review ?? null)
@php($issuesFor = function (array $places) use ($review): int {
    if (! $review) return 0;
    return collect($review['issues'])->filter(fn ($i) => collect($places)->contains(fn ($p) => str_starts_with($i['where'], $p)))->count();
})
@php($creationSteps = [
    1 => ['label' => 'Basic info', 'icon' => 'file-text', 'route' => route('admin.questionnaires.show-details', $questionnaire),
          'issues' => $issuesFor(['Questionnaire details'])],
    2 => ['label' => 'Sections & questions', 'icon' => 'list-tree', 'route' => route('admin.questionnaires.sections.index', $questionnaire),
          'issues' => $issuesFor(['Sections', 'Section "', 'Questions', 'Answers'])],
    3 => ['label' => 'Scoring', 'icon' => 'chart-no-axes-combined', 'route' => route('admin.questionnaires.scoring', $questionnaire),
          'issues' => $issuesFor(['Scoring', 'Result scale'])],
    4 => ['label' => 'Result levels', 'icon' => 'gauge', 'route' => route('admin.questionnaires.result-levels', $questionnaire),
          'issues' => $issuesFor(['Result ranges'])],
    5 => ['label' => 'Review & publish', 'icon' => 'circle-check', 'route' => route('admin.questionnaires.review', $questionnaire),
          'issues' => 0],
])
<nav aria-label="Questionnaire setup progress">
    <ol class="creation-progress">
        @foreach($creationSteps as $number => $creationStep)
            @php($attention = $review && $creationStep['issues'] > 0)
            @php($done = $review && $number < 5 && ! $attention && ($number !== 2 || $review['summary']['questions'] > 0) && ($number !== 4 || $review['summary']['ranges'] > 0))
            <li class="{{ $number === $step ? 'current' : ($attention ? 'attention' : ($done ? 'done' : ($number < $step ? 'visited' : ''))) }}">
                <a href="{{ $creationStep['route'] }}" @if($number === $step) aria-current="step" @endif title="{{ $attention ? $creationStep['issues'].' to fix' : '' }}">
                    <span class="creation-progress-number">{{ $attention ? '!' : ($done && $number !== $step ? '✓' : $number) }}</span>
                    <i data-lucide="{{ $creationStep['icon'] }}"></i>
                    <span>{{ $creationStep['label'] }}@if($attention)<small class="creation-progress-hint"> — {{ $creationStep['issues'] }} to fix</small>@endif</span>
                </a>
            </li>
        @endforeach
    </ol>
</nav>
<style>
    .questionnaire-creation-step .panel{border-radius:14px}.questionnaire-creation-step .button:not(.button-secondary):not(.button-danger){border-radius:8px;background:#2877be;box-shadow:0 4px 12px rgba(40,119,190,.17)}.questionnaire-creation-step .button:not(.button-secondary):not(.button-danger):hover{background:#2169aa}
    .creation-details-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(210px,.8fr);gap:14px;align-items:start}.creation-details-summary h2{font-size:1rem;margin:0 0 12px}.creation-details-summary h2:not(:first-child){margin-top:23px}.creation-details-summary div{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:9px 0;color:#60728b;font-size:.78rem}.creation-details-summary strong{color:#183a60}.creation-details-summary .badge{color:#6047a0;background:#f0eafb}
    .creation-progress{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;list-style:none;margin:0;padding:13px;border:1px solid #dce7f5;border-radius:13px;background:#fff;box-shadow:0 8px 22px rgba(45,83,130,.06)}
    .creation-progress li{position:relative;min-width:0}.creation-progress li:not(:last-child)::after{content:"";position:absolute;top:17px;right:-5px;width:9px;height:1px;background:#afc6df}
    .creation-progress a{display:flex;align-items:center;gap:8px;min-height:34px;color:#65758b;text-decoration:none;font-size:.78rem;font-weight:650}.creation-progress a:hover{color:#1d6eb5}.creation-progress-number{display:grid;place-items:center;width:30px;height:30px;flex:0 0 30px;border:1px solid #bed3eb;border-radius:50%;background:#edf5ff;color:#2d6ba8;font-size:.76rem}.creation-progress svg{width:17px;height:17px;flex:0 0 17px}.creation-progress-hint{font-weight:500;color:#b4562d}
    .creation-progress .current a{color:#143c69}.creation-progress .current .creation-progress-number{border-color:#2876be;background:#2876be;color:#fff}.creation-progress .visited .creation-progress-number{border-color:#aac9e9;background:#ddecfc;color:#225f9b}.creation-progress .done .creation-progress-number{border-color:#9ed7c2;background:#e3f7ee;color:#14846b}.creation-progress .attention .creation-progress-number{border-color:#f0c39a;background:#fff1e3;color:#b4562d}
    @media(max-width:860px){.creation-progress{grid-template-columns:repeat(3,minmax(0,1fr))}.creation-progress li:nth-child(3)::after{display:none}.creation-details-layout{grid-template-columns:1fr}}
    @media(max-width:520px){.creation-progress{grid-template-columns:repeat(2,minmax(0,1fr));gap:5px;padding:9px}.creation-progress li:nth-child(2)::after{display:none}.creation-progress li:nth-child(3)::after{display:block}.creation-progress a{gap:5px;font-size:.68rem}.creation-progress svg{display:none}.creation-progress-number{width:25px;height:25px;flex-basis:25px}}
</style>
