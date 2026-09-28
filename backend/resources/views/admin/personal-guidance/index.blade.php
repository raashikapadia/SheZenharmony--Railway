@extends('layouts.admin')
@section('title', 'Daily Affirmations')
@section('body')
@php
    $typeLabels = ['affirmation' => 'Affirmation', 'quote' => 'Motivational quote'];
    $statusBadge = ['published' => 'active', 'draft' => '', 'unpublished' => ''];
@endphp
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel">
        <div class="page-intro">
            <div>
                <h2>✨ Daily Affirmations</h2>
                <p class="muted">Motivational quotes and daily affirmations shown to every student. Looking for coping strategies or advice? See <a href="{{ route('admin.guidance.index') }}">Guidance</a>. Looking for everyday wellbeing suggestions? See <a href="{{ route('admin.wellbeing-tips.index') }}">Wellbeing Tips</a>.</p>
            </div>
            <a class="button" href="{{ route('admin.personal-guidance.create') }}"><span>+</span>Add guidance</a>
        </div>
        <p class="muted" style="margin-top:-8px"><a href="{{ route('admin.personal-guidance-categories.index') }}">Manage categories →</a></p>

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
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="table-wrap"><table>
            <thead><tr><th>Content</th><th>Type</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>
                        <div class="item-title">{{ Str::limit($item->content, 90) }}</div>
                        @if($item->author)<span class="muted">— {{ $item->author }}</span>@endif
                    </td>
                    <td>{{ $typeLabels[$item->type] ?? ucfirst($item->type) }}</td>
                    <td>{{ $item->categoryName() ?: '—' }}</td>
                    <td><span class="badge {{ $statusBadge[$item->status] ?? '' }}">{{ $item->status === 'published' ? 'Published' : 'Draft' }}</span></td>
                    <td><div class="actions">
                        <a class="button button-secondary" href="{{ route('admin.personal-guidance.preview', $item) }}">Preview</a>
                        <a class="button button-secondary" href="{{ route('admin.personal-guidance.edit', $item) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.personal-guidance.destroy', $item) }}" onsubmit="return confirm('Delete this guidance?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5">Nothing here yet. Add an affirmation or a motivational quote to get started.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div style="margin-top:16px">{{ $items->links() }}</div>
    </section>
</main>
@endsection
