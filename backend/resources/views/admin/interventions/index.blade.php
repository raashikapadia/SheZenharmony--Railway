@extends('layouts.admin')
@section('title', 'Interventions')
@section('body')
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel">
        <div class="page-intro"><div><h2>Intervention Library</h2><p class="muted">Manage the activities and resources shown after assessments.</p></div><a class="button" href="{{ route('admin.interventions.create') }}"><span>＋</span>Add intervention</a></div>
        <div class="table-wrap"><table><thead><tr><th>Resource</th><th>Type</th><th>Stress level</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse($interventions as $intervention)
            <tr><td><div class="item-title">{{ $intervention->title }}</div><span class="muted">{{ Str::limit($intervention->description, 80) }}</span></td><td>{{ ucfirst($intervention->content_type) }}</td><td>{{ $intervention->stress_level ?: 'All levels' }}</td><td><span class="badge {{ $intervention->is_active ? 'active' : '' }}">{{ $intervention->is_active ? 'Active' : 'Inactive' }}</span></td><td><div class="actions"><a class="button button-secondary" href="{{ route('admin.interventions.edit', $intervention) }}">Edit</a><form method="POST" action="{{ route('admin.interventions.destroy', $intervention) }}" onsubmit="return confirm('Delete this intervention?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td></tr>
        @empty<tr><td colspan="5">No interventions configured.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</main>
@endsection
