@extends('layouts.admin')
@section('title', $category->exists ? 'Edit category' : 'Add category')
@section('body')
<main class="content">
    <a class="backlink" href="{{ route('admin.personal-guidance-categories.index') }}">← Categories</a>
    <h1>{{ $category->exists ? 'Edit category' : 'Add category' }}</h1>
    <p class="muted">This appears wherever an admin assigns a category to a tip, quote, or affirmation, and as a filter chip in the student app.</p>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <form method="POST" action="{{ $category->exists ? route('admin.personal-guidance-categories.update', $category) : route('admin.personal-guidance-categories.store') }}">
        @csrf
        @if($category->exists) @method('PUT') @endif

        <label for="name">Name</label>
        <input id="name" name="name" type="text" maxlength="100" value="{{ old('name', $category->name) }}" required placeholder="e.g. Anxiety">

        <label for="description" style="margin-top:16px">Description <span class="muted">(optional, admin-only)</span></label>
        <textarea id="description" name="description" maxlength="1000" placeholder="A short note for other admins about when to use this category.">{{ old('description', $category->description) }}</textarea>

        <label class="remember" style="margin-top:12px">
            <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $category->exists ? $category->is_active : true))>
            Active — offerable when editing a tip, quote, or affirmation
        </label>
        <p class="muted" style="font-size:.8rem">Turning this off hides it from the picker without deleting it or affecting items that already use it.</p>

        <div class="actions" style="margin-top:18px">
            <button class="button" type="submit">Save category</button>
            <a class="button button-secondary" href="{{ route('admin.personal-guidance-categories.index') }}">Cancel</a>
        </div>
    </form>
</main>
@endsection
