@extends('layouts.admin')
@section('title', $guidance->exists ? 'Edit guidance' : 'Add guidance')
@section('body')
@php
    $type = old('type', $guidance->type ?? 'affirmation');
@endphp
<main class="content">
    <a class="backlink" href="{{ route('admin.personal-guidance.index') }}">← Daily Affirmations</a>
    <h1>{{ $guidance->exists ? 'Edit guidance' : 'Add guidance' }}</h1>
    <p class="muted">This appears to every student under ✨ Daily Affirmations. Looking to add a coping strategy or piece of advice instead? Use <a href="{{ route('admin.guidance.index') }}">Guidance</a>.</p>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <form method="POST" action="{{ $guidance->exists ? route('admin.personal-guidance.update', $guidance) : route('admin.personal-guidance.store') }}">
        @csrf
        @if($guidance->exists) @method('PUT') @endif

        <label for="type">Content type</label>
        <select id="type" name="type" required onchange="pgToggleAuthor()">
            <option value="affirmation" @selected($type === 'affirmation')>Affirmation</option>
            <option value="quote" @selected($type === 'quote')>Motivational quote</option>
        </select>

        <label for="content" style="margin-top:16px">
            <span data-pg-label="affirmation">Affirmation text</span>
        </label>
        <textarea id="content" name="content" required maxlength="2000" placeholder="e.g. I am capable of handling whatever today brings.">{{ old('content', $guidance->content) }}</textarea>

        <div id="pg-author-field">
            <label for="author">Author / attribution</label>
            <input id="author" name="author" type="text" maxlength="255" value="{{ old('author', $guidance->author) }}" placeholder="e.g. Maya Angelou">
            <p class="muted" style="font-size:.8rem;margin-top:4px">Required for a motivational quote. Ignored for affirmations.</p>
        </div>

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

        <label class="remember" style="margin-top:16px">
            <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $guidance->exists ? $guidance->status === 'published' : true))>
            Published — visible to students
        </label>

        <div class="actions" style="margin-top:18px">
            <button class="button" type="submit">Save guidance</button>
            <a class="button button-secondary" href="{{ route('admin.personal-guidance.index') }}">Cancel</a>
        </div>
    </form>
</main>
<script>
    function pgToggleAuthor() {
        var type = document.getElementById('type').value;
        var label = document.querySelector('[data-pg-label]');
        label.textContent = type === 'quote' ? 'Quote text' : 'Affirmation text';
        document.getElementById('pg-author-field').style.display = type === 'affirmation' ? 'none' : 'block';
    }
    pgToggleAuthor();
</script>
@endsection
