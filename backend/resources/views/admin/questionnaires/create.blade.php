@extends('layouts.admin')
@section('title', 'New questionnaire')
@section('body')
<main class="content stack">
    <a class="backlink" href="{{ route('admin.questionnaires.index') }}">← Questionnaire Management</a>

    <div>
        <h1 style="margin:0 0 4px">New questionnaire</h1>
        <p class="lede">Three screens: details, then sections &amp; questions, then review &amp; publish. It stays a private draft until you publish.</p>
    </div>

    @include('admin.questionnaires._wizard', ['step' => 1])

    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <section class="panel">
        <div class="panel-head"><h2>Step 1 — Details</h2><p class="lede">Name it and set the client's result scale. Result ranges, sections and questions come next.</p></div>
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
                <label>Client result scale <span class="muted">— the scale results are reported on, as given by the client (e.g. 0 to 40, or 10 to 50)</span></label>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input name="result_scale_min" type="number" value="{{ old('result_scale_min', 0) }}" style="max-width:120px" required aria-label="Scale minimum">
                    <span class="muted">to</span>
                    <input name="result_scale_max" type="number" value="{{ old('result_scale_max') }}" placeholder="e.g. 40" style="max-width:120px" required aria-label="Scale maximum">
                </div>
                <span class="muted">Whatever the questions add up to is converted onto this scale automatically, so it never needs changing when questions are added or removed. You'll define the result ranges (Low, Moderate, …) on it in the editor.</span>
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
