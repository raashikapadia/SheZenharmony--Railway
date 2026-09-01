@extends('layouts.admin')
@section('title', 'Video Activities')
@section('body')
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel">
        <div class="page-intro"><div><h2>Video Activities</h2><p class="muted">Manage YouTube and TikTok resources shown alongside guided wellbeing content.</p></div><a class="button" href="{{ route('admin.wellbeing_activities.create') }}"><span>+</span>Add video activity</a></div>
        <div class="table-wrap"><table><thead><tr><th>Title</th><th>Type</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        @forelse($activities as $activity)
            <tr><td><div class="item-title">{{ $activity->title }}</div><span class="muted">{{ Str::limit($activity->description, 80) }}</span></td><td>{{ ucfirst($activity->video_type) }}</td><td>{{ $activity->category ?: 'General' }}</td><td><span class="badge {{ $activity->is_active ? 'active' : '' }}">{{ $activity->is_active ? 'Active' : 'Inactive' }}</span></td><td><div class="actions"><a class="button button-secondary" href="{{ route('admin.wellbeing_activities.edit', $activity) }}">Edit</a><form method="POST" action="{{ route('admin.wellbeing_activities.destroy', $activity) }}" onsubmit="return confirm('Delete this video activity?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td></tr>
        @empty<tr><td colspan="5">No video activities configured.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</main>
@endsection
