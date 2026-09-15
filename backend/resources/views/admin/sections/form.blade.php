@extends('layouts.admin')
@section('title', $section->exists ? 'Edit section' : 'Add section')
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">← Back to {{ $questionnaire->title }}</a>
    <div>
        <h1 style="margin:0 0 4px">{{ $section->exists ? 'Edit section' : 'Add section' }}</h1>
        <p class="lede">Students see the section name, and the description if you write one, before its questions.</p>
    </div>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <section class="panel form-panel" style="max-width:720px">
        <form class="stack-sm" method="POST" action="{{ $section->exists ? route('admin.questionnaires.sections.update', [$questionnaire, $section]) : route('admin.questionnaires.sections.store', $questionnaire) }}">
            @csrf @if($section->exists)@method('PUT')@endif

            <div><label for="title">Section name</label>
            <input id="title" name="title" type="text" value="{{ old('title', $section->title) }}" placeholder="e.g. Emotional Wellbeing" required autofocus></div>

            <div><label for="description">Description <span class="muted">(optional)</span></label>
            <textarea id="description" name="description" rows="3" placeholder="A sentence to introduce this part of the questionnaire.">{{ old('description', $section->description) }}</textarea></div>

            {{-- Weight and visibility are needed by the scoring engine but not
                 by most admins; the defaults are right for a standard
                 questionnaire. --}}
            <details class="advanced" @if($errors->has('category_weight')) open @endif>
                <summary>More options</summary>
                {{-- Kept for per-section reporting; it does not change the overall score. --}}
                <input type="hidden" name="category_weight" value="{{ old('category_weight', $section->category_weight ?? 1) }}">
                <label class="remember" style="margin-top:12px"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $section->exists ? $section->is_active : true))> Shown to students</label>
            </details>

            <div class="actions" style="justify-content:flex-end">
                <a class="button button-secondary" href="{{ route('admin.questionnaires.sections.index', $questionnaire) }}">Cancel</a>
                <button class="button" type="submit">Save section</button>
            </div>
        </form>
    </section>
</main>
@endsection
