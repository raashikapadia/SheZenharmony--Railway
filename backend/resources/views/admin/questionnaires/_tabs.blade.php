{{-- Keep this navigation presentation-only. Contextual questionnaire routes
     use the current questionnaire, or the first available version on Overview. --}}
@php($tabQuestionnaire = $questionnaire ?? (isset($versions) ? $versions->first() : null))
@php($overviewActive = request()->routeIs('admin.questionnaires.index')
        || request()->routeIs('admin.questionnaires.preview')
        || request()->routeIs('admin.questionnaires.trash'))
@php($builderActive = request()->routeIs('admin.questionnaires.sections.*')
        || request()->routeIs('admin.questionnaires.edit')
        || request()->routeIs('admin.questionnaires.create')
        || request()->routeIs('admin.questionnaires.builder'))
<nav class="questionnaire-tabs" aria-label="Questionnaire Management">
    <a class="button {{ $overviewActive ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.index') }}"><i data-lucide="layout-dashboard"></i>Overview</a>
    @if($tabQuestionnaire)
        <a class="button {{ request()->routeIs('admin.questionnaires.show-details') || request()->routeIs('admin.questionnaires.details') ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.show-details', $tabQuestionnaire) }}"><i data-lucide="file-text"></i>Details</a>
        <a class="button {{ $builderActive ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.sections.index', $tabQuestionnaire) }}"><i data-lucide="list-tree"></i>Sections &amp; Questions</a>
        <a class="button {{ request()->routeIs('admin.questionnaires.scoring') ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.scoring', $tabQuestionnaire) }}"><i data-lucide="chart-no-axes-combined"></i>Scoring</a>
        <a class="button {{ request()->routeIs('admin.questionnaires.review') ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.review', $tabQuestionnaire) }}"><i data-lucide="shield-check"></i>Review &amp; Publish</a>
    @else
        <a class="button button-secondary" href="{{ route('admin.questionnaires.builder') }}"><i data-lucide="file-text"></i>Details</a>
        <a class="button button-secondary" href="{{ route('admin.questionnaires.builder') }}"><i data-lucide="list-tree"></i>Sections &amp; Questions</a>
        <a class="button button-secondary" href="{{ route('admin.questionnaires.builder') }}"><i data-lucide="chart-no-axes-combined"></i>Scoring</a>
        <a class="button button-secondary" href="{{ route('admin.questionnaires.builder') }}"><i data-lucide="shield-check"></i>Review &amp; Publish</a>
    @endif
</nav>
