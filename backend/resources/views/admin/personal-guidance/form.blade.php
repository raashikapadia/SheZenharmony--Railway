@extends('layouts.admin')
@section('title', $guidance->exists ? 'Edit guidance' : 'Add guidance')
@section('body')
@php
    $type = old('type', $guidance->type ?? 'affirmation');
    $suggestedCategories = ['Motivation', 'Confidence', 'Study', 'Career', 'Personal Growth', 'Self-Belief', 'Success', 'Resilience', 'Leadership', 'Life', 'Other'];
    $toLocal = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
@endphp
<main class="content">
    <a href="{{ route('admin.personal-guidance.index') }}">← Personal Guidance</a>
    <h1>{{ $guidance->exists ? 'Edit guidance' : 'Add guidance' }}</h1>
    <p class="muted">This appears on the student Home Page as a small moment of encouragement. It is not a wellbeing activity or a stress intervention.</p>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <form method="POST" action="{{ $guidance->exists ? route('admin.personal-guidance.update', $guidance) : route('admin.personal-guidance.store') }}">
        @csrf
        @if($guidance->exists) @method('PUT') @endif

        <label for="type">Content type</label>
        <select id="type" name="type" required onchange="pgToggleAuthor()">
            <option value="affirmation" @selected($type === 'affirmation')>Affirmation</option>
            <option value="quote" @selected($type === 'quote')>Motivational quote</option>
            <option value="guidance" @selected($type === 'guidance')>Admin guidance</option>
        </select>

        <label for="content" style="margin-top:16px">
            <span data-pg-label="affirmation">Affirmation text</span>
        </label>
        <textarea id="content" name="content" required maxlength="2000" placeholder="e.g. I am capable of handling whatever today brings.">{{ old('content', $guidance->content) }}</textarea>

        <div id="pg-author-field">
            <label for="author">Author / attribution</label>
            <input id="author" name="author" type="text" maxlength="255" value="{{ old('author', $guidance->author) }}" placeholder="e.g. Maya Angelou">
            <p class="muted" style="font-size:.8rem;margin-top:4px">Required for a motivational quote. Optional for admin guidance (leave blank to show it unsigned). Ignored for affirmations.</p>
        </div>

        <div class="field-row" style="margin-top:16px">
            <div>
                <label for="category">Category</label>
                <input id="category" name="category" type="text" list="pg-categories" maxlength="100" value="{{ old('category', $guidance->category) }}" placeholder="e.g. Confidence">
                <datalist id="pg-categories">
                    @foreach($suggestedCategories as $category)<option value="{{ $category }}">@endforeach
                </datalist>
            </div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    @foreach(['draft' => 'Draft', 'published' => 'Published', 'unpublished' => 'Unpublished'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $guidance->status ?? 'draft') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field-row">
            <div>
                <label for="publish_at">Publish date/time <span class="muted">(optional)</span></label>
                <input id="publish_at" name="publish_at" type="datetime-local" value="{{ old('publish_at', $toLocal($guidance->publish_at)) }}">
            </div>
            <div>
                <label for="expires_at">Expiry date/time <span class="muted">(optional)</span></label>
                <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at', $toLocal($guidance->expires_at)) }}">
            </div>
        </div>
        <p class="muted" style="font-size:.8rem">No publish date means it is live as soon as it is published. No expiry means it stays until unpublished.</p>

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
        label.textContent = type === 'quote' ? 'Quote text' : (type === 'guidance' ? 'Guidance text' : 'Affirmation text');
        document.getElementById('pg-author-field').style.display = type === 'affirmation' ? 'none' : 'block';
    }
    pgToggleAuthor();
</script>
@endsection
