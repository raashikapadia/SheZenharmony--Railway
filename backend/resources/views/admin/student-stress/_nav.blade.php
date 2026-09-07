{{-- Stress Level Assessment area tabs. Reporting only — it never
     duplicates questionnaire questions, scoring or configuration. --}}
<nav aria-label="Stress Level Assessment" style="display:flex;flex-wrap:wrap;gap:6px">
    <a class="button {{ request()->routeIs('admin.student-stress.overview') ? '' : 'button-secondary' }}" href="{{ route('admin.student-stress.overview') }}">Overview</a>
    <a class="button {{ request()->routeIs('admin.student-stress.index') || request()->routeIs('admin.student-stress.show') || request()->routeIs('admin.student-stress.assessment') ? '' : 'button-secondary' }}" href="{{ route('admin.student-stress.index') }}">Results</a>
    <a class="button {{ request()->routeIs('admin.student-stress.analytics') ? '' : 'button-secondary' }}" href="{{ route('admin.student-stress.analytics') }}">Analytics</a>
</nav>
