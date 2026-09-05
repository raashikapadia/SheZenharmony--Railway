@extends('layouts.admin')
@section('title', 'Personal Guidance')
@section('body')
@php
    $typeLabels = ['affirmation' => 'Affirmation', 'quote' => 'Motivational quote', 'guidance' => 'Admin guidance'];
    $statusBadge = ['published' => 'active', 'draft' => '', 'unpublished' => ''];
@endphp
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel">
        <div class="page-intro">
            <div>
                <h2>Personal Guidance</h2>
                <p class="muted">Affirmations, motivational quotes, and short admin notes shown on the student Home Page. Only published items inside their schedule appear to students.</p>
            </div>
            <a class="button" href="{{ route('admin.personal-guidance.create') }}"><span>+</span>Add guidance</a>
        </div>

        <form method="GET" class="field-row" style="margin-bottom:18px;border:0;padding:0">
            <div>
                <label for="type">Type</label>
                <select id="type" name="type" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach(['draft' => 'Draft', 'published' => 'Published', 'unpublished' => 'Unpublished'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="category">Category</label>
                <select id="category" name="category" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="table-wrap"><table>
            <thead><tr><th>Content</th><th>Type</th><th>Category</th><th>Schedule</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>
                        <div class="item-title">{{ Str::limit($item->content, 90) }}</div>
                        @if($item->author)<span class="muted">— {{ $item->author }}</span>@endif
                    </td>
                    <td>{{ $typeLabels[$item->type] ?? ucfirst($item->type) }}</td>
                    <td>{{ $item->category ?: '—' }}</td>
                    <td>
                        <span class="muted">
                            {{ $item->publish_at?->format('d M Y') ?? 'Now' }}
                            @if($item->expires_at) → {{ $item->expires_at->format('d M Y') }}@endif
                        </span>
                    </td>
                    <td><span class="badge {{ $statusBadge[$item->status] ?? '' }}">{{ ucfirst($item->status) }}</span></td>
                    <td><div class="actions">
                        <a class="button button-secondary" href="{{ route('admin.personal-guidance.edit', $item) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.personal-guidance.destroy', $item) }}" onsubmit="return confirm('Delete this guidance?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6">No Personal Guidance yet. Add an affirmation, quote, or short note to get started.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div style="margin-top:16px">{{ $items->links() }}</div>
    </section>
</main>
@endsection
