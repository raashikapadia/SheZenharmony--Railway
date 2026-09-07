@extends('layouts.admin')
@section('title', 'New questionnaire')
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaire Management</a>

    <div>
        <h1 style="margin:0 0 4px">New questionnaire</h1>
        <p class="lede">Three quick steps. Name it now, then add its sections and questions, then publish it — or keep it as a draft until you're ready.</p>
    </div>

    @include('admin.questionnaires._wizard', ['step' => 1])

    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <section class="panel">
        <div class="panel-head"><h2>Step 1 — Name your questionnaire</h2></div>
        <form class="stack-sm" method="POST" action="{{ route('admin.questionnaires.store') }}">@csrf
            <div>
                <label for="title">Title</label>
                <input id="title" name="title" value="{{ old('title') }}" placeholder="e.g. SheZen Wellbeing Questionnaire" required autofocus>
            </div>

            <div>
                <label for="description">Description <span class="muted">(shown to students on the intro screen)</span></label>
                <textarea id="description" name="description" placeholder="One or two sentences explaining what this check-in is for.">{{ old('description') }}</textarea>
            </div>

            <div>
                <label for="published_at">Go live at <span class="muted">(optional — pick a date &amp; time now, or decide later)</span></label>
                <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at') }}">
            </div>

            <p class="lede">It's created as a <strong>draft</strong>. Nothing reaches students until you publish it on the last step.</p>

            <div class="actions">
                <button class="button" type="submit">Create &amp; add sections →</button>
                <a class="button button-secondary" href="{{ route('admin.questionnaires.index') }}">Cancel</a>
            </div>
        </form>
    </section>
</main>
@endsection
