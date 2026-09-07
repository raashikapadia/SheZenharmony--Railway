@extends('layouts.admin')
@section('title', 'Analytics · Questionnaire Management')
@section('body')
<main class="content stack">
    @include('admin.questionnaires._tabs')

    <div>
        <h1 style="margin:0 0 4px">Analytics</h1>
        <p class="lede">Live aggregates across every completed assessment — counts and averages only, no student is identifiable.</p>
    </div>

    @if($primary)
        <section class="panel stack">
            <h3>Questionnaire structure</h3>
            <p class="lede">The current questionnaire, <strong>{{ $primary->title }}</strong> (v{{ $primary->version }}, {{ $primary->status }}).</p>
            <div class="meta-grid">
                <div class="tile"><span class="k">Questionnaires</span><span class="v">{{ $questionnaireCount }}</span></div>
                <div class="tile"><span class="k">Sections</span><span class="v">{{ $primary->sections_count ?? $structureSections->count() }}</span></div>
                <div class="tile"><span class="k">Questions</span><span class="v">{{ $primary->questions_count ?? $structureSections->sum('questions_count') }}</span></div>
                @if($structure['weighted'])
                    <div class="tile"><span class="k">Max weighted</span><span class="v">{{ rtrim(rtrim(number_format($structure['max_weighted'], 2), '0'), '.') }}</span></div>
                    <div class="tile"><span class="k">Max raw</span><span class="v">{{ $structure['max_raw'] }}</span></div>
                @else
                    <div class="tile"><span class="k">Max score</span><span class="v">{{ $structure['max_raw'] }}</span></div>
                @endif
                <div class="tile"><span class="k">Result levels</span><span class="v">{{ $structure['result_levels'] }}</span></div>
                @if($structure['stress_ranges'] > 0)<div class="tile"><span class="k">Stress ranges</span><span class="v">{{ $structure['stress_ranges'] }}</span></div>@endif
                <div class="tile"><span class="k">Last published</span><span class="v sm">{{ $primary->published_at?->format('j M Y') ?? '—' }}</span></div>
                <div class="tile"><span class="k">Last updated</span><span class="v sm">{{ $primary->updated_at?->format('j M Y, g:i A') ?? '—' }}</span></div>
            </div>

            @if($structureSections->isNotEmpty())
                <div>
                    <h4 style="margin:0 0 6px">By section</h4>
                    <div class="table-wrap"><table>
                        <thead><tr><th>#</th><th>Section</th><th>Weight</th><th>Questions</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach($structureSections as $section)
                            <tr>
                                <td>{{ $section->position }}</td>
                                <td>{{ $section->title }}</td>
                                <td>{{ rtrim(rtrim(number_format($section->category_weight, 2), '0'), '.') }}</td>
                                <td>{{ $section->questions_count }}</td>
                                <td><span class="badge {{ $section->is_active ? 'active' : '' }}">{{ $section->is_active ? 'Active' : 'Archived' }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endif
        </section>
    @endif

    @include('admin._analytics', ['routeName' => 'admin.questionnaires.analytics'])
</main>
@endsection
