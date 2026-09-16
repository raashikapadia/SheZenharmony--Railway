@extends('layouts.admin')
@section('title', 'Registered Students')
@section('body')
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
            <table>
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
        <div style="margin-top:18px">{{ $students->links() }}</div>
    </section>
</main>
@endsection
