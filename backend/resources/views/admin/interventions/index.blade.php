@extends('layouts.admin')
@section('title', $configuration['title'])
@section('body')
<main class="content">
    <h1>{{ $configuration['title'] }}</h1>
    @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    <div class="page-intro">
        <div>
            <h2>{{ $configuration['heading'] }}</h2>
            <p class="muted">{{ $configuration['description'] }}</p>
        </div>
        <a class="button" href="{{ route($configuration['route'].'.create') }}"><span>＋</span>Add {{ strtolower($configuration['singular']) }}</a>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Content</th><th>Type</th><th>Student section</th><th>Recommended level</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($interventions as $intervention)
        @php($levels = $intervention->recommendations->map(fn ($rec) => $rec->scoreBand?->label)->filter()->unique()->values())
        <tr>
            <td><div class="item-title">{{ $intervention->title }}</div><span class="muted">{{ Str::limit($intervention->description, 80) }}</span></td>
            <td>{{ Str::headline($intervention->content_type) }}</td>
            <td>{{ $configuration['studentSection'] }}</td>
            <td>{{ $levels->isNotEmpty() ? $levels->join(', ') : 'All levels' }}</td>
            <td><span class="badge {{ $intervention->is_active ? 'active' : '' }}">{{ $intervention->is_active ? 'Published' : 'Draft' }}</span></td>
            <td><div class="actions"><a class="button button-secondary" href="{{ route($configuration['route'].'.edit', $intervention) }}">Edit</a><form method="POST" action="{{ route($configuration['route'].'.destroy', $intervention) }}" onsubmit="return confirm('Delete this {{ strtolower($configuration['singular']) }}?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td>
        </tr>
    @empty
        <tr><td colspan="6">{{ $configuration['empty'] }}</td></tr>
    @endforelse
    </tbody></table></div>
    {{ $interventions->links() }}
</main>
@endsection
