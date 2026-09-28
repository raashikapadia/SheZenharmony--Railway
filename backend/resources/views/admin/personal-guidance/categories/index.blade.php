@extends('layouts.admin')
@section('title', 'Personal Guidance Categories')
@section('body')
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel">
        <div class="page-intro">
            <div>
                <h2>Categories</h2>
                <p class="muted">The categories admins can assign to Tips, Quotes, and Affirmations. Renaming a category updates every item using it; deleting one only removes the label — the item itself stays.</p>
            </div>
            <a class="button" href="{{ route('admin.personal-guidance-categories.create') }}"><span>+</span>Add category</a>
        </div>
        <p class="muted" style="margin-top:-8px"><a href="{{ route('admin.personal-guidance.index') }}">← Back to Personal Guidance</a></p>

        <div class="table-wrap"><table>
            <thead><tr><th>Name</th><th>Description</th><th>In use</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($categories as $category)
                <tr>
                    <td><div class="item-title">{{ $category->name }}</div></td>
                    <td><span class="muted">{{ $category->description ? Str::limit($category->description, 80) : '—' }}</span></td>
                    <td><span class="muted">{{ $category->personal_guidance_count }} item(s)</span></td>
                    <td><span class="badge {{ $category->is_active ? 'active' : '' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><div class="actions">
                        <a class="button button-secondary" href="{{ route('admin.personal-guidance-categories.edit', $category) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.personal-guidance-categories.destroy', $category) }}" onsubmit="return confirm('Delete this category? Items using it will keep their content but lose the category label.')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5">No categories yet. Add one so tips and quotes can be filtered by feeling or topic.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div style="margin-top:16px">{{ $categories->links() }}</div>
    </section>
</main>
@endsection
