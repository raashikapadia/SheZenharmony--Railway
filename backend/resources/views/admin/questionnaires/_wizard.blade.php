@php($step = $step ?? 1)
@php($sectionsHint = $sectionsHint ?? null)
<ol class="wizard-steps" aria-label="Questionnaire setup progress">
    <li class="{{ $step > 1 ? 'done' : ($step === 1 ? 'current' : '') }}">
        <span class="n">{{ $step > 1 ? '✓' : '1' }}</span> Details
    </li>
    <li class="{{ $step > 2 ? 'done' : ($step === 2 ? 'current' : '') }}">
        <span class="n">{{ $step > 2 ? '✓' : '2' }}</span> Sections &amp; questions
        @if($sectionsHint)<span class="wizard-hint">— {{ $sectionsHint }}</span>@endif
    </li>
    <li class="{{ $step === 3 ? 'current' : '' }}">
        <span class="n">3</span> Publish or keep as draft
    </li>
</ol>
