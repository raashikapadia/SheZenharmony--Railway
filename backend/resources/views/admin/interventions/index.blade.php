@extends('layouts.admin')
@section('title', $configuration['title'])
@section('body')
<main class="content">
    @php($isGamesSection = $configuration['route'] === 'admin.positive-engagement.games')
    <h1>{{ $configuration['title'] }}</h1>
    @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @unless($isGamesSection)
    <div class="page-intro">
        <div>
            <h2>{{ $configuration['heading'] }}</h2>
            <p class="muted">{{ $configuration['description'] }}</p>
        </div>
        @if($configuration['route'] !== 'admin.positive-engagement.games')
            <a class="button" href="{{ route($configuration['route'].'.create') }}"><span>＋</span>Add {{ strtolower($configuration['singular']) }}</a>
        @endif
    </div>
    @endunless
    @if($isGamesSection)
        <div class="cards" style="margin-bottom:20px">
            <article class="card"><div class="muted">Total Games</div><div class="metric">4</div></article>
            <article class="card"><div class="muted">Students Played</div><div class="metric">{{ $stats['totalStudentsPlayed'] }}</div></article>
        </div>
        <section class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>Student app games</h2><p class="muted">These games are available in the student app.</p></div><div style="display:flex;flex-wrap:wrap;gap:10px">@foreach($builtInGames as $game)<span class="badge active">{{ $game }}</span>@endforeach</div></section>
        <section class="panel"><div class="panel-head"><h2>Game library</h2><p class="muted">Games shown here are synced to the student app.</p></div>
    @endif
    <div class="table-wrap"><table><thead><tr><th>{{ $isGamesSection ? 'Game' : 'Content' }}</th>@if($isGamesSection)<th>Game ID</th>@endif @if(!$isGamesSection)<th>Type</th><th>Student section</th><th>Recommended level</th>@endif<th>{{ $isGamesSection ? 'App status' : 'Status' }}</th>@if($isGamesSection)<th>Usage records</th>@endif<th>Actions</th></tr></thead><tbody>
    @forelse($interventions as $intervention)
        @php($levels = $intervention->recommendations->map(fn ($rec) => $rec->scoreBand?->label)->filter()->unique()->values())
        <tr>
            <td><div class="item-title">{{ $intervention->title }}</div><span class="muted">{{ Str::limit($intervention->description, 100) ?: 'No description provided.' }}</span></td>
            @if($isGamesSection)<td><span class="badge">#{{ $intervention->id }}</span></td>@endif
            @if(!$isGamesSection)<td>{{ Str::headline($intervention->content_type) }}</td><td>{{ $configuration['studentSection'] }}</td><td>{{ $levels->isNotEmpty() ? $levels->join(', ') : 'All levels' }}</td>@endif
            <td><span class="badge {{ $intervention->is_active ? 'active' : '' }}">{{ $intervention->is_active ? 'Published' : 'Draft' }}</span>@if($isGamesSection)<br><span class="muted">{{ $intervention->students_played_count }} students played</span>@endif</td>
            @if($isGamesSection)
                <td>
                    @forelse($intervention->usages as $usage)
                        <div><strong>{{ $usage->studentIdentity?->displayId() ?? 'Unknown ID' }}</strong> <span class="muted">{{ $usage->started_at?->format('d M Y H:i') }}</span></div>
                    @empty
                        <span class="muted">No plays yet</span>
                    @endforelse
                </td>
            @endif
            <td><div class="actions"><a class="button button-secondary" href="{{ route($configuration['route'].'.edit', $intervention) }}">{{ $isGamesSection ? 'Edit details' : 'Edit' }}</a><form method="POST" action="{{ route($configuration['route'].'.destroy', $intervention) }}" onsubmit="return confirm('Delete this {{ strtolower($configuration['singular']) }}?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td>
        </tr>
    @empty
        <tr><td colspan="{{ $isGamesSection ? 6 : 6 }}">{{ $configuration['empty'] }}</td></tr>
    @endforelse
    </tbody></table></div>
    @if($isGamesSection)</section>@endif
    {{ $interventions->links() }}
</main>
@endsection
