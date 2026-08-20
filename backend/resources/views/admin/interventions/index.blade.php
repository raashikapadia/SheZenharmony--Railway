@extends('layouts.admin')
@section('title', 'Interventions')
@section('body')
<div class="shell"><header class="topbar"><nav class="nav"><a href="{{ route('admin.dashboard') }}">Dashboard</a><a href="{{ route('admin.questions.index') }}">Questions</a><span class="brand">Interventions</span></nav><a class="button button-secondary" href="{{ route('admin.interventions.create') }}">Add intervention</a></header><main class="content">@if(session('status'))<div class="status">{{ session('status') }}</div>@endif
<h1>Intervention management</h1><div class="table-wrap"><table><thead><tr><th>Title</th><th>Type</th><th>Stress level</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($interventions as $intervention)<tr><td>{{ $intervention->title }}</td><td>{{ $intervention->content_type }}</td><td>{{ $intervention->stress_level ?: 'All' }}</td><td>{{ $intervention->is_active ? 'Active' : 'Inactive' }}</td><td><div class="actions"><a class="button button-secondary" href="{{ route('admin.interventions.edit', $intervention) }}">Edit</a><form method="POST" action="{{ route('admin.interventions.destroy', $intervention) }}" onsubmit="return confirm('Delete this intervention?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td></tr>@empty<tr><td colspan="5">No interventions configured.</td></tr>@endforelse
</tbody></table></div>{{ $interventions->links() }}</main></div>
@endsection
