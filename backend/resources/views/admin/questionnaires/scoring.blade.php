@extends('layouts.admin')
@section('title', 'Scoring · '.$questionnaire->title)
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.details', $questionnaire) }}">← Back to Details</a>

    <div>
        <h1 style="margin:0 0 4px">Scoring overview</h1>
        <p class="lede">Read-only summary of how <strong>{{ $questionnaire->title }}</strong> (v{{ $questionnaire->version }}) is scored. Edit weights and ranges in the editor.</p>
    </div>

    @if($validationError)
        <div class="errors"><strong>Needs attention before publishing:</strong> {{ $validationError }}</div>
    @else
        <div class="status">Configuration looks valid for publishing.</div>
    @endif

    <section class="panel">
        <div class="panel-head"><h3>Sections &amp; weights</h3></div>
        @if($questionnaire->sections->isEmpty())
            <p class="lede">No sections — this questionnaire uses flat additive scoring (sum of answer scores → one overall band).</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>#</th><th>Section</th><th>Weight</th><th>Questions</th></tr></thead>
                <tbody>
                @foreach($questionnaire->sections->sortBy('position') as $section)
                    <tr>
                        <td>{{ $section->position }}</td>
                        <td>{{ $section->title }} @unless($section->is_active)<span class="badge">archived</span>@endunless</td>
                        <td>{{ rtrim(rtrim(number_format($section->category_weight, 2), '0'), '.') }}</td>
                        <td>{{ $section->questions_count }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <p class="muted" style="margin:12px 0 0">Raw score with the current questions: <strong>{{ $rawSpan[0] }}–{{ $rawSpan[1] }}</strong>.@if($resultScale) Converted onto the client's result scale <strong>{{ $resultScale[0] }}–{{ $resultScale[1] }}</strong>, which the result ranges below are written on.@endif Section weights only shape the per-section breakdown.</p>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><h3>Overall wellbeing result ranges</h3></div>
        @include('admin.questionnaires._band_table', ['bands' => $overallBands, 'floor' => $scoreSpan[0], 'ceiling' => $scoreSpan[1]])
    </section>

    <section class="panel">
        <div class="panel-head"><h3>Stress result ranges <span class="muted">(0–100)</span></h3></div>
        @if($stressBands->isEmpty())
            <p class="lede">None configured. Only needed if one or more questions are marked “contributes to stress score”.</p>
        @else
            @include('admin.questionnaires._band_table', ['bands' => $stressBands, 'ceiling' => 100])
        @endif
    </section>
</main>
@endsection
