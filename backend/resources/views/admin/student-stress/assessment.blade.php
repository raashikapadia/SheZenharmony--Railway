@extends('layouts.admin')
@section('title', 'Assessment answers')
@section('body')
@php($identity = $assessment->studentIdentity)
<main class="content">
    @include('admin.student-stress._nav')
    <a href="{{ $identity ? route('admin.student-stress.show', $identity) : route('admin.student-stress.index') }}">← Back</a>

    <section class="panel" style="margin-top:16px">
        <div class="page-intro">
            <div>
                <h2 style="font-family:ui-monospace,monospace">{{ $identity?->displayId() ?? 'Anonymous session' }}</h2>
                <p class="muted">
                    {{ $assessment->questionnaire?->title ?? 'Assessment' }}
                    @if($assessment->questionnaire) (v{{ $assessment->questionnaire->version }}) @endif
                    · completed {{ $assessment->completed_at?->format('d M Y H:i') ?? '—' }}
                </p>
            </div>
        </div>

        <div class="field-row">
            <div><label>Overall score</label>
                @if($assessment->overall_weighted_score !== null)
                    {{ rtrim(rtrim(number_format($assessment->overall_weighted_score, 1), '0'), '.') }} / {{ rtrim(rtrim(number_format($assessment->overall_max_weighted_score, 1), '0'), '.') }}
                    <span class="muted">({{ $assessment->total_score }} pts)</span>
                @else
                    {{ $assessment->total_score ?? '—' }}
                @endif
            </div>
            <div><label>Overall %</label>{{ $assessment->overall_percentage !== null ? rtrim(rtrim(number_format($assessment->overall_percentage, 1), '0'), '.').'%' : '—' }}</div>
            <div><label>Result level</label>
                @php($wb = $assessment->wellbeingBand ?? $assessment->scoreBand)
                @if($wb)<span class="badge">{{ $wb->label }}</span>@else — @endif
            </div>
            <div><label>Stress score</label>{{ $assessment->stress_score !== null ? rtrim(rtrim(number_format($assessment->stress_score, 1), '0'), '.').' / 100' : '—' }}
                @if($assessment->stressBand) · <span class="badge">{{ $assessment->stressBand->label }}</span>@endif
            </div>
        </div>
    </section>

    @if($assessment->categoryResults->isNotEmpty())
    <section class="panel" style="margin-top:16px">
        <h3>Category breakdown</h3>
        <div class="table-wrap"><table>
            <thead><tr><th>Category</th><th>Raw</th><th>Range</th><th>%</th><th>Weight</th><th>Weighted</th></tr></thead>
            <tbody>
            @foreach($assessment->categoryResults as $cat)
                <tr>
                    <td>{{ $cat->section_title_snapshot }}</td>
                    <td>{{ rtrim(rtrim(number_format($cat->raw_score, 2), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($cat->min_possible_score, 2), '0'), '.') }}–{{ rtrim(rtrim(number_format($cat->max_possible_score, 2), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($cat->percentage, 1), '0'), '.') }}%</td>
                    <td>{{ rtrim(rtrim(number_format($cat->category_weight, 2), '0'), '.') }}</td>
                    <td>{{ rtrim(rtrim(number_format($cat->weighted_score, 2), '0'), '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </section>
    @endif

    <section class="panel" style="margin-top:16px">
        <h3>Answers <span class="muted">({{ $assessment->responses->count() }})</span></h3>
        <div class="table-wrap"><table>
            <thead><tr><th>#</th><th>Question</th><th>Answer</th><th>Score</th><th>Scored value</th></tr></thead>
            <tbody>
            @forelse($assessment->responses as $i => $response)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $response->question_text_snapshot ?? '—' }}</td>
                    <td>{{ $response->option_text_snapshot ?? $response->answer_text ?? '—' }}</td>
                    <td>{{ $response->score ?? '—' }}</td>
                    <td>{{ $response->scored_value !== null ? rtrim(rtrim(number_format($response->scored_value, 2), '0'), '.') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No stored answers for this assessment.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</main>
@endsection
