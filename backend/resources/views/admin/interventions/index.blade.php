@extends('layouts.admin')
@section('title', 'Support Content')
@section('body')
<main class="content">
    <h1>Support Content</h1>
    @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
    <div class="page-intro">
        <div>
            <h2>Guided activities and reflections</h2>
            <p class="muted">Manage text-guided content shown in Wellbeing Activities and Positive Engagement. Stress-level targeting can also support future result recommendations.</p>
        </div>
        <a class="button" href="{{ route('admin.interventions.create') }}"><span>＋</span>Add support content</a>
    </div>
    <div class="table-wrap"><table><thead><tr><th>Content</th><th>Type</th><th>Student section</th><th>Stress tier</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($interventions as $intervention)
        @php
            $positiveTypes = ['journaling', 'affirmation', 'quiz', 'motivation', 'positive_engagement'];
            $studentSection = in_array($intervention->content_type, $positiveTypes, true) ? 'Positive Engagement' : 'Wellbeing Activities';
        @endphp
        <tr>
            <td><div class="item-title">{{ $intervention->title }}</div><span class="muted">{{ Str::limit($intervention->description, 80) }}</span></td>
            <td>{{ Str::headline($intervention->content_type) }}</td>
            <td>{{ $studentSection }}</td>
            <td>{{ $intervention->stress_level ?: 'All tiers' }}</td>
            <td><span class="badge {{ $intervention->is_active ? 'active' : '' }}">{{ $intervention->is_active ? 'Active' : 'Inactive' }}</span></td>
            <td><div class="actions"><a class="button button-secondary" href="{{ route('admin.interventions.edit', $intervention) }}">Edit</a><form method="POST" action="{{ route('admin.interventions.destroy', $intervention) }}" onsubmit="return confirm('Delete this support content?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form></div></td>
        </tr>
    @empty
        <tr><td colspan="6">No support content configured.</td></tr>
    @endforelse
    </tbody></table></div>
    {{ $interventions->links() }}
</main>
@endsection
