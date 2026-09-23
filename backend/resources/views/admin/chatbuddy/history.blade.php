@extends('layouts.admin')
@section('title', 'Chat Buddy Publication History')
@section('body')
<main class="content"><section class="panel"><a class="backlink" href="{{ route('admin.chatbuddy.releases.index') }}">&larr; Chat Buddy Manager</a><h1>Publication history</h1><table><thead><tr><th>Action</th><th>Release</th><th>Admin</th><th>Time</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->action }}</td><td>{{ $log->release_id }}</td><td>{{ $log->user?->email ?? 'Deleted admin' }}</td><td>{{ $log->created_at }}</td></tr>@empty<tr><td colspan="4" class="muted">No publication changes yet.</td></tr>@endforelse</tbody></table></section></main>
@endsection
