@extends('layouts.admin')
@section('title', 'Helpline Resources')
@section('body')
<main class="content">
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel">
        <div class="page-intro">
            <div>
                <h2>Helpline Resources</h2>
                <p class="muted">Support contacts shown in the student Resource tab. Only active resources reach the app. Lower display-order numbers appear first; the emergency flag highlights a card rather than reordering it.</p>
            </div>
            <a class="button" href="{{ route('admin.resources.create') }}"><span>+</span>Add resource</a>
        </div>

        <form method="GET" class="field-row" style="margin-bottom:18px;border:0;padding:0">
            <div>
                <label for="status">Status</label>
                <select id="status" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach(['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
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
            <thead><tr><th>Resource</th><th>Contact</th><th>Availability</th><th>Category</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($resources as $resource)
                <tr>
                    <td>
                        <div class="item-title">{{ $resource->name }}</div>
                        @if($resource->organisation)<span class="muted">{{ $resource->organisation }}</span>@endif
                        @if($resource->is_emergency)<span class="badge active" style="margin-left:6px">Emergency</span>@endif
                    </td>
                    <td>
                        <div>{{ $resource->phone }}</div>
                        @if($resource->alternate_phone)<span class="muted">{{ $resource->alternate_phone }}</span>@endif
                    </td>
                    <td><span class="muted">{{ $resource->availability ?: '—' }}</span></td>
                    <td>{{ $resource->category ?: '—' }}</td>
                    <td><span class="muted">{{ $resource->position }}</span></td>
                    <td><span class="badge {{ $resource->is_active ? 'active' : '' }}">{{ $resource->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td><div class="actions">
                        <a class="button button-secondary" href="{{ route('admin.resources.edit', $resource) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.resources.destroy', $resource) }}" onsubmit="return confirm('Delete this resource?')">@csrf @method('DELETE')<button class="button button-danger" type="submit">Delete</button></form>
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="7">No helpline resources yet. Add a support contact to show it in the student Resource tab.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div style="margin-top:16px">{{ $resources->links() }}</div>
    </section>
</main>
@endsection
