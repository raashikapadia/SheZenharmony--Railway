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
        {{-- Archive is the only action without a house style: it sits between
             Publish and Delete, so it borrows their solid pill and takes a
             warning amber rather than the danger red. --}}
        <style>
            .game-action-archive{color:#fff;background:#c2761a;box-shadow:0 3px 8px rgba(154,91,22,.2)}
            .game-action-archive:hover{background:#a86413}
            .game-usage{color:#1d3763;font-size:1.05rem;font-weight:700}
            .game-usage.zero{color:#93a3bb;font-weight:600}
        </style>
        <div class="cards" style="margin-bottom:20px">
            <article class="card"><div class="muted">Total Games</div><div class="metric">{{ $stats['totalGames'] }}</div><div class="metric-note">{{ $stats['publishedGames'] }} published</div></article>
            <article class="card"><div class="muted">Students Played</div><div class="metric">{{ $stats['totalStudentsPlayed'] }}</div><div class="metric-note">Counted once per student</div></article>
            <article class="card"><div class="muted">Total Plays</div><div class="metric">{{ $stats['totalPlays'] }}</div><div class="metric-note">Across every game</div></article>
        </div>
        <section class="panel"><div class="panel-head"><h2>Game library</h2><p class="muted">Publish a game to show it in the student app, or archive it to take it away without losing its play history.</p></div>
    @endif
    <div class="table-wrap"><table><thead><tr><th>{{ $isGamesSection ? 'Game' : 'Content' }}</th>@if($isGamesSection)<th>Game ID</th>@endif @if(!$isGamesSection)<th>Type</th><th>Student section</th><th>Recommended level</th>@endif<th>{{ $isGamesSection ? 'App status' : 'Status' }}</th>@if($isGamesSection)<th>Students played</th><th>Total plays</th><th>Last played</th>@endif<th>Actions</th></tr></thead><tbody>
    @forelse($interventions as $intervention)
        @php($levels = $intervention->recommendations->map(fn ($rec) => $rec->scoreBand?->label)->filter()->unique()->values())
        <tr>
            <td><div class="item-title">{{ $intervention->title }}</div><span class="muted">{{ Str::limit($intervention->description, 100) ?: 'No description provided.' }}</span></td>
            @if($isGamesSection)<td><span class="badge">#{{ $intervention->id }}</span></td>@endif
            @if(!$isGamesSection)<td>{{ Str::headline($intervention->content_type) }}</td><td>{{ $configuration['studentSection'] }}</td><td>{{ $levels->isNotEmpty() ? $levels->join(', ') : 'All levels' }}</td>@endif
            <td><span class="badge {{ $intervention->is_active ? 'active' : '' }}">{{ $intervention->is_active ? 'Published' : ($isGamesSection ? 'Archived' : 'Draft') }}</span></td>
            @if($isGamesSection)
                <td><span class="game-usage {{ $intervention->students_played_count ? '' : 'zero' }}">{{ $intervention->students_played_count }}</span></td>
                <td><span class="game-usage {{ $intervention->plays_count ? '' : 'zero' }}">{{ $intervention->plays_count }}</span></td>
                <td>@if($intervention->usages_max_started_at){{ \Illuminate\Support\Carbon::parse($intervention->usages_max_started_at)->format('d M Y H:i') }}@else<span class="muted">Not played yet</span>@endif</td>
            @endif
            <td><div class="actions">
                <a class="button button-secondary" href="{{ route($configuration['route'].'.edit', $intervention) }}">Edit</a>
                @if($isGamesSection)
                    <form method="POST" action="{{ route($configuration['route'].'.visibility', $intervention) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $intervention->is_active ? '0' : '1' }}"><button class="button {{ $intervention->is_active ? 'game-action-archive' : '' }}" type="submit">{{ $intervention->is_active ? 'Archive' : 'Publish' }}</button></form>
                @endif
                @unless($isGamesSection && in_array($intervention->slug, \App\Http\Controllers\Web\AdminGamesController::BUILT_IN_SLUGS, true))
                    <form method="POST" action="{{ route($configuration['route'].'.destroy', $intervention) }}" onsubmit="return confirm('Delete this {{ strtolower($configuration['singular']) }}?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                @endunless
            </div></td>
        </tr>
    @empty
        <tr><td colspan="{{ $isGamesSection ? 7 : 6 }}">{{ $configuration['empty'] }}</td></tr>
    @endforelse
    </tbody></table></div>
    @if($isGamesSection)</section>@endif
    {{ $interventions->links() }}
</main>
@endsection
