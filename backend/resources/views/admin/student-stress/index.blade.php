@extends('layouts.admin')
@section('title', 'Student Stress Scores')
@section('body')
<main class="content">
    <section class="panel">
        <div class="page-intro">
            <div>
                <h2>Student Stress Scores</h2>
                <p class="muted">Latest completed stress result per student, by pseudonymous SheZen ID. Names and emails are never shown here.</p>
            </div>
            <form method="GET" class="actions">
                <select name="band" onchange="this.form.submit()">
                    <option value="">All levels</option>
                    @foreach($bands as $band)
                        <option value="{{ $band->code }}" @selected($bandFilter === $band->code)>{{ $band->label }}</option>
                    @endforeach
                </select>
                <noscript><button class="button" type="submit">Filter</button></noscript>
            </form>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>SheZen ID</th><th>Latest Score</th><th>Level</th><th>Date</th><th></th></tr></thead>
                <tbody>
                @forelse($students as $student)
                    @php($assessment = $student->latestAssessment)
                    <tr>
                        <td><div class="item-title" style="font-family:ui-monospace,monospace;overflow-wrap:anywhere">{{ $student->displayId() }}</div></td>
                        <td>{{ $assessment?->total_score ?? '—' }}</td>
                        <td>
                            @if($assessment?->scoreBand)
                                <span class="badge">{{ $assessment->scoreBand->label }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $assessment?->completed_at?->format('d M Y') ?? '—' }}</td>
                        <td class="actions">
                            <a class="button-link" href="{{ route('admin.student-stress.show', $student) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">No completed stress assessments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px">{{ $students->links() }}</div>
    </section>
</main>
@endsection
