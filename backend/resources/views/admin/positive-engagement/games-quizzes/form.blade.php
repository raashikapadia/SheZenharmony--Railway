@extends('layouts.admin')
@section('title', $quiz->exists ? 'Edit Quiz' : 'Add Quiz')
@section('body')
<main class="content">
    <a class="backlink" href="{{ route('admin.positive-engagement.games-quizzes.index') }}">← Games & Quizzes</a>
    <h1>{{ $quiz->exists ? 'Edit quiz' : 'Add quiz' }}</h1>
    <p class="lede">Quiz content here is separate from questionnaire and stress-management content.</p>
    @if($errors->any())<div class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <form method="POST" action="{{ $quiz->exists ? route('admin.positive-engagement.games-quizzes.update', $quiz) : route('admin.positive-engagement.games-quizzes.store') }}">
        @csrf @if($quiz->exists) @method('PUT') @endif
        <div class="field-row"><div><label for="name">Quiz name</label><input id="name" name="name" type="text" value="{{ old('name', $quiz->name) }}" required></div><div><label for="category">Category</label><input id="category" name="category" type="text" value="{{ old('category', $quiz->category) }}" required></div></div>
        <label for="description">Description</label><textarea id="description" name="description">{{ old('description', $quiz->description) }}</textarea>
        <label for="status">Status</label><select id="status" name="status"><option value="active" @selected(old('status', $quiz->status ?: 'inactive') === 'active')>Active</option><option value="inactive" @selected(old('status', $quiz->status ?: 'inactive') === 'inactive')>Inactive</option></select>
        <hr class="divider">
        <div class="split"><div><h2>Questions</h2><p class="muted">Each question requires four options and one correct answer.</p></div><button class="button button-secondary" type="button" id="add-question">+ Add question</button></div>
        <div id="questions" class="stack" style="margin-top:16px">
            @forelse(old('questions', $questions->toArray()) as $index => $question)
                @include('admin.positive-engagement.games-quizzes._question', ['index' => $index, 'question' => $question])
            @empty
                @include('admin.positive-engagement.games-quizzes._question', ['index' => 0, 'question' => []])
            @endforelse
        </div>
        <div class="actions" style="margin-top:20px"><button class="button" type="submit">Save quiz</button><a class="button button-secondary" href="{{ route('admin.positive-engagement.games-quizzes.index') }}">Cancel</a></div>
    </form>
</main>
<template id="question-template">@include('admin.positive-engagement.games-quizzes._question', ['index' => '__INDEX__', 'question' => []])</template>
<script>
(() => {
    const list = document.getElementById('questions');
    const template = document.getElementById('question-template').innerHTML;
    let nextIndex = list.children.length;
    document.getElementById('add-question').addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', template.replaceAll('__INDEX__', nextIndex++));
    });
    list.addEventListener('click', (event) => {
        if (event.target.matches('[data-remove-question]')) {
            event.target.closest('[data-question]').remove();
        }
    });
})();
</script>
@endsection
