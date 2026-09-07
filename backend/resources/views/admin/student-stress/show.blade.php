@extends('layouts.admin')
@section('title', 'Student Stress Profile')
@section('body')
<main class="content">
    @include('admin.student-stress._nav')
    <a href="{{ route('admin.student-stress.index') }}">← Results</a>
    <section class="panel" style="margin-top:16px">
        <div class="page-intro">
            <div>
                <h2 style="font-family:ui-monospace,monospace">{{ $student->displayId() }}</h2>
                <p class="muted">Pseudonymous student profile. Authentication name and email are not shown here.</p>
            </div>
        </div>
        @php($latest = $student->latestAssessment)
        <div class="field-row">
            <div><label>Latest Score</label>{{ $latest ? $latest->total_score.' / 100' : '—' }}</div>
            <div><label>Result Level</label>
                @if($latest?->scoreBand)<span class="badge">{{ $latest->scoreBand->label }}</span>@else{{ '—' }}@endif
            </div>
            <div><label>Latest Assessment</label>{{ $latest?->completed_at?->format('d M Y') ?? '—' }}</div>
            <div><label>Completed Assessments</label>{{ $history->count() }}</div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px">
        <h3>Assessment History</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Score</th><th>Level</th><th>Stress</th><th></th></tr></thead>
                <tbody>
                @forelse($history as $assessment)
                    <tr>
                        <td>{{ $assessment->completed_at?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $assessment->total_score ?? '—' }}</td>
                        <td>{{ $assessment->wellbeingBand?->label ?? $assessment->scoreBand?->label ?? $assessment->stress_level ?? '—' }}</td>
                        <td>{{ $assessment->stress_score !== null ? rtrim(rtrim(number_format($assessment->stress_score, 1), '0'), '.').' / 100' : '—' }}</td>
                        <td class="actions"><a class="button-link" href="{{ route('admin.student-stress.assessment', $assessment) }}">View answers</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">No completed assessments for this student yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
