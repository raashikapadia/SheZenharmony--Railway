@php($creationSteps = [
    1 => ['label' => 'Details', 'icon' => 'file-text', 'route' => route('admin.questionnaires.show-details', $questionnaire)],
    2 => ['label' => 'Sections & Questions', 'icon' => 'list-tree', 'route' => route('admin.questionnaires.sections.index', $questionnaire)],
    3 => ['label' => 'Scoring', 'icon' => 'chart-no-axes-combined', 'route' => route('admin.questionnaires.scoring', $questionnaire)],
    4 => ['label' => 'Review & Publish', 'icon' => 'circle-check', 'route' => route('admin.questionnaires.review', $questionnaire)],
])
<nav aria-label="New questionnaire setup progress">
    <ol class="creation-progress">
        @foreach($creationSteps as $number => $creationStep)
            <li class="{{ $number === $step ? 'current' : ($number < $step ? 'visited' : '') }}">
                <a href="{{ $creationStep['route'] }}" @if($number === $step) aria-current="step" @endif>
                    <span class="creation-progress-number">{{ $number }}</span>
                    <i data-lucide="{{ $creationStep['icon'] }}"></i>
                    <span>{{ $creationStep['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ol>
</nav>
<style>
    .questionnaire-creation-step .panel{border-radius:14px}.questionnaire-creation-step .button:not(.button-secondary):not(.button-danger){border-radius:8px;background:#2877be;box-shadow:0 4px 12px rgba(40,119,190,.17)}.questionnaire-creation-step .button:not(.button-secondary):not(.button-danger):hover{background:#2169aa}
    .creation-details-layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(210px,.8fr);gap:14px;align-items:start}.creation-details-summary h2{font-size:1rem;margin:0 0 12px}.creation-details-summary h2:not(:first-child){margin-top:23px}.creation-details-summary div{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:9px 0;color:#60728b;font-size:.78rem}.creation-details-summary strong{color:#183a60}.creation-details-summary .badge{color:#6047a0;background:#f0eafb}
    .creation-progress{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;list-style:none;margin:0;padding:13px;border:1px solid #dce7f5;border-radius:13px;background:#fff;box-shadow:0 8px 22px rgba(45,83,130,.06)}
    .creation-progress li{position:relative;min-width:0}.creation-progress li:not(:last-child)::after{content:"";position:absolute;top:17px;right:-5px;width:9px;height:1px;background:#afc6df}
    .creation-progress a{display:flex;align-items:center;gap:8px;min-height:34px;color:#65758b;text-decoration:none;font-size:.78rem;font-weight:650}.creation-progress a:hover{color:#1d6eb5}.creation-progress-number{display:grid;place-items:center;width:30px;height:30px;flex:0 0 30px;border:1px solid #bed3eb;border-radius:50%;background:#edf5ff;color:#2d6ba8;font-size:.76rem}.creation-progress svg{width:17px;height:17px;flex:0 0 17px}
    .creation-progress .current a{color:#143c69}.creation-progress .current .creation-progress-number{border-color:#2876be;background:#2876be;color:#fff}.creation-progress .visited .creation-progress-number{border-color:#aac9e9;background:#ddecfc;color:#225f9b}
    @media(max-width:720px){.creation-progress{grid-template-columns:repeat(2,minmax(0,1fr))}.creation-progress li:nth-child(2)::after{display:none}.creation-details-layout{grid-template-columns:1fr}}
    @media(max-width:430px){.creation-progress{gap:5px;padding:9px}.creation-progress a{gap:5px;font-size:.68rem}.creation-progress svg{display:none}.creation-progress-number{width:25px;height:25px;flex-basis:25px}}
</style>
