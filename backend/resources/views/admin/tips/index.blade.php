@extends('layouts.admin')
@section('title', 'Wellbeing Tips')
@section('body')
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel">
        <div class="page-intro">
            <div>
                <h2>☀️ Daily Wellbeing Tips</h2>
                <p class="muted">Small everyday suggestions shown to every student. Give it a title, write a short description, and save.</p>
            </div>
            <a class="button" href="{{ route('admin.wellbeing-tips.create') }}"><span>+</span>Add tip</a>
        </div>
        <p class="muted" style="margin-top:-8px"><a href="{{ route('admin.personal-guidance-categories.index') }}">Manage categories →</a></p>

        <form method="GET" class="field-row" style="margin-bottom:18px;border:0;padding:0">
            <div>
                <label for="search">Search</label>
                <input id="search" name="search" type="text" value="{{ $search }}" placeholder="Search title or description">
            </div>
            <div>
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $categoryId === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="align-self:end">
                <button class="button button-secondary" type="submit">Filter</button>
            </div>
        </form>

        <div class="table-wrap"><table>
            <thead><tr><th>Tip</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td>
                        <div class="item-title">{{ $item->title ?: Str::limit($item->content, 60) }}</div>
                        <span class="muted">{{ Str::limit($item->content, 90) }}</span>
                    </td>
                    <td>{{ $item->categoryName() ?: '—' }}</td>
                    <td><span class="badge {{ $item->status === 'published' ? 'active' : '' }}">{{ $item->status === 'published' ? 'Published' : 'Draft' }}</span></td>
                    <td><div class="actions">
                        <a class="button button-secondary" href="{{ route('admin.wellbeing-tips.edit', $item) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.wellbeing-tips.destroy', $item) }}" onsubmit="return confirm('Delete this tip?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="4">No tips yet. Add a small everyday wellbeing suggestion to get started.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div style="margin-top:16px">{{ $items->links() }}</div>
    </section>
</main>
@endsection
