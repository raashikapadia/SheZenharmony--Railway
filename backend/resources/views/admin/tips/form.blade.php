@extends('layouts.admin')
@section('title', $tip->exists ? 'Edit tip' : 'Add tip')
@section('body')
<main class="content">
    <a class="backlink" href="{{ route('admin.wellbeing-tips.index') }}">← Wellbeing Tips</a>
    <h1>{{ $tip->exists ? 'Edit tip' : 'Add tip' }}</h1>
    <p class="muted">Write the tip and save. Every published item appears to students under ☀️ Daily Wellbeing Tips.</p>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <form method="POST" action="{{ $tip->exists ? route('admin.wellbeing-tips.update', $tip) : route('admin.wellbeing-tips.store') }}">
        @csrf
        @if($tip->exists) @method('PUT') @endif

        <label for="title">Title</label>
        <input id="title" name="title" type="text" maxlength="255" value="{{ old('title', $tip->title) }}" required placeholder="e.g. Drink some water">

        <label for="content" style="margin-top:16px">Short description</label>
        <textarea id="content" name="content" required maxlength="2000" placeholder="e.g. Staying hydrated helps your focus and your mood — keep a bottle nearby today.">{{ old('content', $tip->content) }}</textarea>

        <div class="field-row" style="margin-top:16px">
            <div>
                <label for="content_category_id">Category <span class="muted">(optional)</span></label>
                <select id="content_category_id" name="content_category_id">
                    <option value="">— none —</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('content_category_id', $tip->content_category_id) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <p class="muted" style="margin-top:6px;font-size:.8rem"><a href="{{ route('admin.personal-guidance-categories.index') }}">Manage categories →</a></p>
            </div>
        </div>

        <label for="steps" style="margin-top:16px">Suggested action <span class="muted">(optional)</span></label>
        <input id="steps" name="steps" type="text" maxlength="500" value="{{ old('steps', $tip->steps) }}" placeholder="e.g. Fill a bottle and keep it on your desk">

        <label class="remember" style="margin-top:16px">
            <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $tip->exists ? $tip->status === 'published' : true))>
            Published — visible to students
        </label>

        <div class="actions" style="margin-top:18px">
            <button class="button" type="submit">Save tip</button>
            <a class="button button-secondary" href="{{ route('admin.wellbeing-tips.index') }}">Cancel</a>
        </div>
    </form>
</main>
@endsection
