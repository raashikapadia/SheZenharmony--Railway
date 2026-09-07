{{-- Shared analytics body. One dataset ($a from AssessmentAnalytics), in-page
     sub-tabs, rendered under both Questionnaire Management and Stress Level
     Assessment with $lens = 'wellbeing' | 'stress'. --}}
@php($subtabs = [
    'overall' => 'Overall',
    'categories' => 'Categories',
    'questions' => 'Questions',
    'demographics' => 'Demographics',
    'vs' => 'Stress vs Wellbeing',
    'trends' => 'Trends',
    'factors' => 'Stress factors',
    'protective' => 'Protective factors',
])
@php($tab = in_array($tab ?? 'overall', array_keys($subtabs), true) ? $tab : 'overall')

<nav aria-label="Analytics views" style="display:flex;flex-wrap:wrap;gap:6px">
    @foreach($subtabs as $key => $label)
        <a class="button {{ $tab === $key ? '' : 'button-secondary' }}" href="{{ route($routeName, ['tab' => $key]) }}">{{ $label }}</a>
    @endforeach
</nav>

<section class="panel stack">
@switch($tab)
    @case('overall')
        <h3>Overall</h3>
        <div class="meta-grid">
            <div class="tile"><span class="k">Completed</span><span class="v">{{ $a['totals']['completed'] }}</span></div>
            <div class="tile"><span class="k">Started</span><span class="v">{{ $a['totals']['started'] }}</span></div>
            <div class="tile"><span class="k">Completion</span><span class="v">{{ $a['totals']['completion_rate'] !== null ? $a['totals']['completion_rate'].'%' : '—' }}</span></div>
            <div class="tile"><span class="k">Students</span><span class="v">{{ $a['totals']['students'] }}</span></div>
            <div class="tile"><span class="k">Last 30 days</span><span class="v">{{ $a['totals']['last_30_days'] }}</span></div>
            <div class="tile"><span class="k">Avg wellbeing</span><span class="v">{{ $a['averages']['overall_percentage'] !== null ? $a['averages']['overall_percentage'].'%' : '—' }}</span></div>
            <div class="tile"><span class="k">Avg stress</span><span class="v">{{ $a['averages']['stress_score'] !== null ? $a['averages']['stress_score'].' / 100' : '—' }}</span></div>
        </div>

        @php($blocks = $lens === 'stress'
                ? ['Stress result levels' => $a['stress_bands'], 'Wellbeing result levels' => $a['wellbeing_bands']]
                : ['Wellbeing result levels' => $a['wellbeing_bands'], 'Stress result levels' => $a['stress_bands']])
        @foreach($blocks as $heading => $rows)
            <div>
                <h4 style="margin:0 0 6px">{{ $heading }}</h4>
                @if($rows->isEmpty())
                    <p class="lede">No {{ str_contains($heading, 'Stress') ? 'stress-scored' : 'completed' }} assessments yet.</p>
                @else
                    <div class="table-wrap"><table>
                        <thead><tr><th>Level</th><th>Assessments</th><th>Share</th></tr></thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr><td>{{ $row['label'] }}</td><td>{{ $row['total'] }}</td><td>{{ $a['totals']['completed'] > 0 ? round($row['total'] / $a['totals']['completed'] * 100) : 0 }}%</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        @endforeach

        @if($a['by_questionnaire']->isNotEmpty())
            <div>
                <h4 style="margin:0 0 6px">By questionnaire version</h4>
                <div class="table-wrap"><table>
                    <thead><tr><th>Questionnaire</th><th>Completed</th></tr></thead>
                    <tbody>
                    @foreach($a['by_questionnaire'] as $row)
                        <tr><td>{{ $row['title'] }} @if($row['version'])(v{{ $row['version'] }})@endif</td><td>{{ $row['total'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endif
        @break

    @case('categories')
        <h3>Categories</h3>
        @if($a['categories']->isEmpty())
            <p class="lede">No category results yet — this needs a questionnaire with weighted sections.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Category</th><th>Responses</th><th>Average %</th></tr></thead>
                <tbody>
                @foreach($a['categories'] as $row)
                    <tr><td>{{ $row['title'] }}</td><td>{{ $row['responses'] }}</td><td>{{ $row['avg_percentage'] }}%</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        @break

    @case('questions')
        <h3>Questions</h3>
        <p class="lede">Average answer score per question, across all completed assessments (highest first).</p>
        @if($a['questions']->isEmpty())
            <p class="lede">No answers recorded yet.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Question</th><th>Answers</th><th>Avg score</th></tr></thead>
                <tbody>
                @foreach($a['questions'] as $row)
                    <tr><td>{{ $row['question'] }}</td><td>{{ $row['answers'] }}</td><td>{{ $row['avg_score'] }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        @break

    @case('demographics')
        <h3>Demographics</h3>
        <p class="lede">From registration data. Groups with fewer than 5 assessments are hidden.</p>
        @foreach(['year_of_study' => 'Year of study', 'gender' => 'Gender', 'country' => 'Country'] as $key => $label)
            <div>
                <h4 style="margin:0 0 6px">{{ $label }}</h4>
                @if($a['demographics'][$key]->isEmpty())
                    <p class="lede">Not enough data.</p>
                @else
                    <div class="table-wrap"><table>
                        <thead><tr><th>{{ $label }}</th><th>Assessments</th><th>Avg wellbeing</th><th>Avg stress</th></tr></thead>
                        <tbody>
                        @foreach($a['demographics'][$key] as $row)
                            <tr><td>{{ $row['bucket'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['avg_wellbeing'] !== null ? $row['avg_wellbeing'].'%' : '—' }}</td><td>{{ $row['avg_stress'] ?? '—' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        @endforeach
        @break

    @case('vs')
        <h3>Stress vs Wellbeing</h3>
        <p class="lede">Average stress score within each wellbeing result level — expect stress to fall as wellbeing rises.</p>
        @if($a['stress_vs_wellbeing']->isEmpty())
            <p class="lede">Needs completed assessments with both a wellbeing band and a stress score.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Wellbeing level</th><th>Assessments</th><th>Avg wellbeing</th><th>Avg stress</th></tr></thead>
                <tbody>
                @foreach($a['stress_vs_wellbeing'] as $row)
                    <tr><td>{{ $row['band'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['avg_wellbeing'] !== null ? $row['avg_wellbeing'].'%' : '—' }}</td><td>{{ $row['avg_stress'] ?? '—' }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        @break

    @case('trends')
        <h3>Trends</h3>
        <p class="lede">Longitudinal trends appear here once assessments span multiple check-in periods. Nothing to plot yet.</p>
        @break

    @case('factors')
        <h3>Stress factors</h3>
        <p class="lede">The questions that feed the stress score are configured per question in the <a href="{{ route('admin.questionnaires.builder') }}">Questionnaire Builder</a> (Advanced scoring settings → “Contributes to stress score”). Once those are set and assessments come in, the highest-scoring stress questions will surface here.</p>
        @break

    @case('protective')
        <h3>Protective factors</h3>
        <p class="lede">Reverse-scored and high-average wellbeing questions act as protective factors. Mark them in the Builder’s Advanced scoring settings; results will summarise here.</p>
        @break
@endswitch
</section>
