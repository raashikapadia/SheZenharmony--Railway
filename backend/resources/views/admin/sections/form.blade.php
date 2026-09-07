@extends('layouts.admin')
@section('title', $section->exists ? 'Edit section' : 'Add section')
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">← Back to editor</a>
    <div>
        <h1 style="margin:0 0 4px">{{ $section->exists ? 'Edit section' : 'Add section' }}</h1>
        <p class="lede">A section is a wellbeing category inside <strong>{{ $questionnaire->title }}</strong>.</p>
    </div>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <form class="stack-sm" method="POST" action="{{ $section->exists ? route('admin.questionnaires.sections.update', [$questionnaire, $section]) : route('admin.questionnaires.sections.store', $questionnaire) }}">
        @csrf @if($section->exists)@method('PUT')@endif

        <div><label for="title">Section title</label>
        <input id="title" name="title" type="text" value="{{ old('title', $section->title) }}" required></div>

        <div><label for="description">Description <span class="muted">(optional, shown to students)</span></label>
        <textarea id="description" name="description">{{ old('description', $section->description) }}</textarea></div>

        <div class="field-row">
            <div><label for="position">Position</label>
            <input id="position" name="position" type="number" min="0" value="{{ old('position', $section->position ?? 0) }}"></div>
            <div><label for="category_weight">Category weight</label>
            <input id="category_weight" name="category_weight" type="number" step="0.01" min="0.01" value="{{ old('category_weight', $section->category_weight ?? 1) }}" required>
            <span class="muted">How much this category contributes to the overall wellbeing score.</span></div>
        </div>

        <label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $section->exists ? $section->is_active : true))> Active</label>

        <div class="actions">
            <button class="button" type="submit">Save section</button>
            <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">Cancel</a>
        </div>
    </form>
</main>
@endsection
