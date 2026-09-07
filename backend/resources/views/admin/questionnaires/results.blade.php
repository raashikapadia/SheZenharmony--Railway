@extends('layouts.admin')
@section('title', 'Results · Questionnaire Management')
@section('body')
<main class="content stack">
    @include('admin.questionnaires._tabs')

    <div>
        <h1 style="margin:0 0 4px">Results</h1>
        <p class="lede">Every completed assessment, newest first, by pseudonymous SheZen ID. Open one to see all answers and the category breakdown.</p>
    </div>

    <section class="panel">
        @if($assessments->isEmpty())
            <p class="lede">No completed assessments yet.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>SheZen ID</th><th>Completed</th><th>Version</th><th>Wellbeing</th><th>Stress</th><th></th></tr></thead>
                <tbody>
                @foreach($assessments as $item)
                    @php($wb = $item->wellbeingBand ?? $item->scoreBand)
                    <tr>
                        <td style="font-family:ui-monospace,monospace">{{ $item->studentIdentity?->displayId() ?? '—' }}</td>
                        <td>{{ $item->completed_at?->format('j M Y') ?? '—' }}</td>
                        <td>{{ $item->questionnaire ? 'v'.$item->questionnaire->version : '—' }}</td>
                        <td>
                            {{ $item->overall_weighted_score !== null ? rtrim(rtrim(number_format($item->overall_weighted_score, 1), '0'), '.') : $item->total_score }}
                            @if($wb)<span class="badge">{{ $wb->label }}</span>@endif
                        </td>
                        <td>{{ $item->stress_score !== null ? rtrim(rtrim(number_format($item->stress_score, 1), '0'), '.').' / 100' : '—' }}
                            @if($item->stressBand)<span class="badge">{{ $item->stressBand->label }}</span>@endif
                        </td>
                        <td class="actions"><a class="button button-secondary" href="{{ route('admin.student-stress.assessment', $item) }}">View answers</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div style="margin-top:16px">{{ $assessments->links() }}</div>
        @endif
    </section>
</main>
@endsection
