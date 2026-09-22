@extends('layouts.admin')
@section('title', $section->exists ? 'Edit section' : 'Add section')
@section('body')
@php($customWeights = ! $questionnaire->usesEqualSectionWeights())
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

            @if($customWeights)
                <div style="max-width:260px"><label for="category_weight">Weight <span class="muted">— how much this section counts toward the result</span></label>
                <input id="category_weight" name="category_weight" type="number" step="0.01" min="0.01" value="{{ old('category_weight', $section->category_weight ?? 1) }}" required>
                <small class="muted">Weights are compared with the other sections, e.g. 20 / 30 / 50. Switch to equal weights on the Scoring step to have them worked out automatically.</small></div>
            @else
                {{-- Under equal weighting the engine derives every weight, so
                     the stored one only matters if the admin later switches. --}}
                <input type="hidden" name="category_weight" value="{{ old('category_weight', $section->category_weight ?? 1) }}">
                <p class="muted" style="margin:0;font-size:.88rem">Sections share the result equally. To give this section more or less weight, choose custom weights on the <a href="{{ route('admin.questionnaires.scoring', $questionnaire) }}">Scoring</a> step.</p>
            @endif

            <details class="advanced" @if($errors->has('is_active')) open @endif>
                <summary>More options</summary>
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
