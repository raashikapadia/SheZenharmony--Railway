@extends('layouts.admin')
@section('title', $guidance->exists ? 'Edit guidance' : 'Add guidance')
@section('body')
<main class="content">
    <a class="backlink" href="{{ route('admin.guidance.index') }}">← Guidance</a>
    <h1>{{ $guidance->exists ? 'Edit guidance' : 'Add guidance' }}</h1>
    <p class="muted">Write the advice and save. Every published item appears to students under 🌿 Advice &amp; Coping.</p>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <form method="POST" action="{{ $guidance->exists ? route('admin.guidance.update', $guidance) : route('admin.guidance.store') }}">
        @csrf
        @if($guidance->exists) @method('PUT') @endif

        <label for="title">Title</label>
        <input id="title" name="title" type="text" maxlength="255" value="{{ old('title', $guidance->title) }}" required placeholder="e.g. Take a short break">

        <label for="content" style="margin-top:16px">Advice</label>
        <textarea id="content" name="content" required maxlength="2000" placeholder="e.g. If you're feeling overwhelmed, step away for a few minutes, take some slow breaths, and give yourself time to reset.">{{ old('content', $guidance->content) }}</textarea>

        <div class="field-row" style="margin-top:16px">
            <div>
                <label for="content_category_id">Category <span class="muted">(optional)</span></label>
                <select id="content_category_id" name="content_category_id">
                    <option value="">— none —</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('content_category_id', $guidance->content_category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <p class="muted" style="margin-top:6px;font-size:.8rem"><a href="{{ route('admin.personal-guidance-categories.index') }}">Manage categories →</a></p>
            </div>
        </div>

        <label for="steps" style="margin-top:16px">"Try this" action <span class="muted">(optional)</span></label>
        <input id="steps" name="steps" type="text" maxlength="500" value="{{ old('steps', $guidance->steps) }}" placeholder="e.g. Take three slow breaths before your next class">

        <label for="resource_url" style="margin-top:16px">External resource link <span class="muted">(optional)</span></label>
        <input id="resource_url" name="resource_url" type="url" maxlength="2048" value="{{ old('resource_url', $guidance->resource_url) }}" placeholder="https://example.org/helpful-article">

        <label class="remember" style="margin-top:16px">
            <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $guidance->exists ? $guidance->status === 'published' : true))>
            Published — visible to students
        </label>

        <div class="actions" style="margin-top:18px">
            <button class="button" type="submit">Save guidance</button>
            <a class="button button-secondary" href="{{ route('admin.guidance.index') }}">Cancel</a>
        </div>
    </form>
</main>
@endsection
