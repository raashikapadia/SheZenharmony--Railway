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
        <div class="table-wrap">
            <table>
                <thead><tr><th>SheZen ID</th><th>Status</th><th>Country</th><th>Gender</th><th>Employment</th><th>Baseline</th><th>Registered</th></tr></thead>
                <tbody>
                @forelse($students as $student)
                    <tr>
                        <td><div class="item-title" style="font-family:ui-monospace,monospace;overflow-wrap:anywhere">{{ $student->displayId() }}</div></td>
                        <td><span class="badge {{ $student->user?->account_status === 'active' ? 'active' : '' }}">{{ $student->user?->account_status ?? 'unavailable' }}</span></td>
                        <td>{{ $student->profile?->country ?? '—' }}</td>
                        <td>{{ $student->profile?->gender ?? '—' }}</td>
                        <td>{{ $student->profile?->employment_status ?? '—' }}</td>
                        <td><span class="badge {{ $student->has_completed_required_assessment ? 'active' : '' }}">{{ $student->has_completed_required_assessment ? 'Completed' : 'Required' }}</span></td>
                        <td>{{ $student->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">No registered students yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px">{{ $students->links() }}</div>
    </section>
</main>
@endsection
