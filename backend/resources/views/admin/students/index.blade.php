@extends('layouts.admin')
@section('title', 'Registered Students')
@section('body')
<style>
    .student-pagination nav > div:first-child { display: none; }
    .student-pagination nav > div:last-child { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .student-pagination nav p { margin: 0; color: var(--muted); font-size: .85rem; }
    .student-pagination nav > div:last-child > div:last-child > span { display: inline-flex; align-items: center; flex-wrap: wrap; }
    .student-pagination nav a, .student-pagination nav span[aria-current="page"] > span, .student-pagination nav span[aria-disabled="true"] > span { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; min-height: 36px; padding: 7px 10px; border: 1px solid var(--line); background: #fff; color: #24476d; text-decoration: none; font-size: .85rem; }
    .student-pagination nav a:hover { background: #edf5ff; }
    .student-pagination nav a:focus-visible { outline: 2px solid #2877be; outline-offset: 2px; }
    .student-pagination nav span[aria-current="page"] > span { background: #2877be; border-color: #2877be; color: #fff; }
    .student-pagination nav span[aria-disabled="true"] > span { color: #8c9aaa; }
    .student-pagination nav svg { display: block; width: 20px; height: 20px; }
    @media (max-width: 600px) { .student-pagination nav > div:last-child { justify-content: center; } }

    .student-table { table-layout: fixed; }
    .student-table th, .student-table td { overflow-wrap: anywhere; }
    .student-table th:nth-child(1), .student-table td:nth-child(1) { width: 16%; }
    .student-table th:nth-child(2), .student-table td:nth-child(2) { width: 13%; }
    .student-table th:nth-child(3), .student-table td:nth-child(3),
    .student-table th:nth-child(4), .student-table td:nth-child(4),
    .student-table th:nth-child(5), .student-table td:nth-child(5) { width: 12%; }
    .student-table th:nth-child(6), .student-table td:nth-child(6) { width: 14%; }
    .student-table th:nth-child(7), .student-table td:nth-child(7) { width: 13%; white-space: nowrap; }
    .student-table th:nth-child(8), .student-table td:nth-child(8) { width: 8%; white-space: nowrap; }
    .student-table td.actions { display: table-cell; }
    .student-table td.actions .button-link { display: inline-block; }
    @media (max-width: 900px) {
        .student-table th:nth-child(1), .student-table td:nth-child(1),
        .student-table th:nth-child(3), .student-table td:nth-child(3),
        .student-table th:nth-child(4), .student-table td:nth-child(4),
        .student-table th:nth-child(5), .student-table td:nth-child(5) { display: none; }
        .student-table th:nth-child(2), .student-table td:nth-child(2) { width: 27%; }
        .student-table th:nth-child(6), .student-table td:nth-child(6) { width: 27%; }
        .student-table th:nth-child(7), .student-table td:nth-child(7) { width: 31%; }
        .student-table th:nth-child(8), .student-table td:nth-child(8) { width: 15%; }
    }
</style>
<main class="content">
    <section class="panel">
        <div class="page-intro">
            <div>
                <h2>Registered Students</h2>
                <p class="muted">Pseudonymous student accounts. Authentication names and emails are not shown here.</p>
            </div>
        </div>
        <form method="GET" class="field-row" style="margin-bottom:18px">
            <select name="country"><option value="">All countries</option>@foreach($countries as $value)<option value="{{ $value }}" @selected(($filters['country'] ?? '') === $value)>{{ $value }}</option>@endforeach</select>
            <select name="gender"><option value="">All genders</option>@foreach($genders as $value)<option value="{{ $value }}" @selected(($filters['gender'] ?? '') === $value)>{{ $value }}</option>@endforeach</select>
            <select name="employment"><option value="">All employment</option>@foreach($employments as $value)<option value="{{ $value }}" @selected(($filters['employment'] ?? '') === $value)>{{ $value }}</option>@endforeach</select>
            <select name="baseline"><option value="">All baseline statuses</option><option value="completed" @selected(($filters['baseline'] ?? '') === 'completed')>Completed</option><option value="required" @selected(($filters['baseline'] ?? '') === 'required')>Required</option></select>
            <div class="student-filter-actions" style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
                <button class="button" type="submit">Filter</button>
                <a class="button button-secondary" href="{{ route('admin.students.index') }}">Reset filters</a>
            </div>
        </form>
        <div class="table-wrap">
            <table class="student-table">
                <thead><tr><th>SheZen ID</th><th>Status</th><th>Country</th><th>Gender</th><th>Employment</th><th>Baseline</th><th>Registered</th><th></th></tr></thead>
                <tbody>
                @forelse($students as $student)
                    <tr>
                        <td><div class="item-title" style="font-family:ui-monospace,monospace;overflow-wrap:anywhere">{{ $student->displayId() }}</div></td>
                        <td><span class="badge {{ $student->user?->account_status === 'active' ? 'active' : '' }}">{{ $student->user?->account_status === 'suspended' ? 'On hold' : ($student->user?->account_status ?? 'unavailable') }}</span></td>
                        <td>{{ $student->profile?->country ?? '—' }}</td>
                        <td>{{ $student->profile?->gender ?? '—' }}</td>
                        <td>{{ $student->profile?->employment_status ?? '—' }}</td>
                        <td><span class="badge {{ $student->has_completed_required_assessment ? 'active' : '' }}">{{ $student->has_completed_required_assessment ? 'Completed' : 'Required' }}</span></td>
                        <td>{{ $student->created_at->format('d M Y') }}</td>
                        <td class="actions">
                            <a class="button-link" href="{{ route('admin.students.show', $student) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No registered students yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="student-pagination" style="margin-top:18px">{{ $students->links() }}</div>
    </section>
</main>
@endsection
