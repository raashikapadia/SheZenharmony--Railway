@extends('layouts.admin')
@section('title', 'Overview · Stress Level Assessment')
@section('body')
<main class="content stack">
    @include('admin.student-stress._nav')

    <div>
        <h1 style="margin:0 0 4px">Stress Level Assessment</h1>
        <p class="lede">Reporting and analysis for the stress component of the wellbeing questionnaire. Questions and scoring are configured in Questionnaire Management.</p>
    </div>

    <section class="panel stack">
        <h3>Snapshot</h3>
        <div class="meta-grid">
            <div class="tile"><span class="k">Completed</span><span class="v">{{ $a['totals']['completed'] }}</span></div>
            <div class="tile"><span class="k">Students</span><span class="v">{{ $a['totals']['students'] }}</span></div>
            <div class="tile"><span class="k">Last 30 days</span><span class="v">{{ $a['totals']['last_30_days'] }}</span></div>
            <div class="tile"><span class="k">Avg stress</span><span class="v">{{ $a['averages']['stress_score'] !== null ? $a['averages']['stress_score'].' / 100' : '—' }}</span></div>
            <div class="tile"><span class="k">Avg wellbeing</span><span class="v">{{ $a['averages']['overall_percentage'] !== null ? $a['averages']['overall_percentage'].'%' : '—' }}</span></div>
        </div>

        <div>
            <h4 style="margin:0 0 6px">Stress result levels</h4>
            @if($a['stress_bands']->isEmpty())
                <p class="lede">No stress-scored assessments yet. Mark questions as “contributes to stress score” in the Questionnaire Builder.</p>
            @else
                <div class="table-wrap"><table>
                    <thead><tr><th>Level</th><th>Assessments</th></tr></thead>
                    <tbody>
                    @foreach($a['stress_bands'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['total'] }}</td></tr>@endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        <div class="actions">
            <a class="button" href="{{ route('admin.student-stress.index') }}">View results</a>
            <a class="button button-secondary" href="{{ route('admin.student-stress.analytics') }}">Open analytics</a>
        </div>
    </section>
</main>
@endsection
