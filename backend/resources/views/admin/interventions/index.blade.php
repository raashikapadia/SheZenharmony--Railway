@extends('layouts.admin')
@section('title', $configuration['title'])
@section('body')
<main class="content">
    @php($isGamesSection = $configuration['route'] === 'admin.positive-engagement.games')
    <h1>{{ $configuration['title'] }}</h1>
    @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    @if($isGamesSection)
        <section class="panel"><div class="panel-head"><h2>Future Development</h2><p class="muted">Games administration will be available in a future update.</p></div></section>
    @else
    <div class="page-intro">
        <div>
            <h2>{{ $configuration['heading'] }}</h2>
            <p class="muted">{{ $configuration['description'] }}</p>
        </div>
        @if($configuration['route'] !== 'admin.positive-engagement.games')
            <a class="button" href="{{ route($configuration['route'].'.create') }}"><span>＋</span>Add {{ strtolower($configuration['singular']) }}</a>
        @endif
    </div>
    @if($isGamesSection)<section class="panel"><div class="panel-head"><h2>Game library</h2><p class="muted">These games are available in the student app. Edit the details or visibility of an existing game.</p></div>@endif
    <div class="table-wrap"><table><thead><tr><th>{{ $isGamesSection ? 'Game' : 'Content' }}</th>@if(!$isGamesSection)<th>Type</th><th>Student section</th><th>Recommended level</th>@endif<th>{{ $isGamesSection ? 'App status' : 'Status' }}</th><th>Actions</th></tr></thead><tbody>
    @forelse($interventions as $intervention)
        @php($levels = $intervention->recommendations->map(fn ($rec) => $rec->scoreBand?->label)->filter()->unique()->values())
        <tr>
            <td><div class="item-title">{{ $intervention->title }}</div><span class="muted">{{ Str::limit($intervention->description, 100) ?: 'No description provided.' }}</span></td>
            @if(!$isGamesSection)<td>{{ Str::headline($intervention->content_type) }}</td><td>{{ $configuration['studentSection'] }}</td><td>{{ $levels->isNotEmpty() ? $levels->join(', ') : 'All levels' }}</td>@endif
            <td><span class="badge {{ $intervention->is_active ? 'active' : '' }}">{{ $intervention->is_active ? 'Published' : 'Draft' }}</span></td>
            <td><div class="actions"><a class="button button-secondary" href="{{ route($configuration['route'].'.edit', $intervention) }}">{{ $isGamesSection ? 'Edit details' : 'Edit' }}</a><form method="POST" action="{{ route($configuration['route'].'.destroy', $intervention) }}" onsubmit="return confirm('Delete this {{ strtolower($configuration['singular']) }}?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td>
        </tr>
    @empty
        <tr><td colspan="{{ $isGamesSection ? 4 : 6 }}">{{ $configuration['empty'] }}</td></tr>
    @endforelse
    </tbody></table></div>
    @if($isGamesSection)</section>@endif
    {{ $interventions->links() }}
    @endif
</main>
@endsection
