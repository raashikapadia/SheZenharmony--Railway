{{-- Questionnaire Management area tabs. These are the ONLY questionnaire
     navigation — question, section, option and scoring management all live
     inside the Builder tab. --}}
@php($isBuilder = request()->routeIs('admin.questionnaires.sections.*')
        || request()->routeIs('admin.questionnaires.edit')
        || request()->routeIs('admin.questionnaires.create')
        || request()->routeIs('admin.questionnaires.scoring')
        || request()->routeIs('admin.questionnaires.builder'))
<nav aria-label="Questionnaire Management" style="display:flex;flex-wrap:wrap;gap:6px">
    <a class="button {{ request()->routeIs('admin.questionnaires.index') || request()->routeIs('admin.questionnaires.preview') || request()->routeIs('admin.questionnaires.trash') ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.index') }}">Overview</a>
    <a class="button {{ $isBuilder ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.builder') }}">Questionnaire Builder</a>
    <a class="button {{ request()->routeIs('admin.questionnaires.results') ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.results') }}">Results</a>
    <a class="button {{ request()->routeIs('admin.questionnaires.analytics') ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.analytics') }}">Analytics</a>
    <a class="button {{ request()->routeIs('admin.questionnaires.versions') ? '' : 'button-secondary' }}" href="{{ route('admin.questionnaires.versions') }}">Versions</a>
</nav>
