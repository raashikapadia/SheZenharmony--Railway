@extends('layouts.admin')
@section('title', $intervention->exists ? 'Edit support content' : 'Add support content')
@section('body')
<main class="content">
    <a href="{{ route('admin.interventions.index') }}">← Support Content</a>
    <h1>{{ $intervention->exists ? 'Edit support content' : 'Add support content' }}</h1>
    <p class="muted">The content type determines where this appears in the student application.</p>
    @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $intervention->exists ? route('admin.interventions.update', $intervention) : route('admin.interventions.store') }}">
        @csrf
        @if($intervention->exists) @method('PUT') @endif
        <label for="title">Title</label>
        <input id="title" name="title" type="text" value="{{ old('title', $intervention->title) }}" required>
        <label for="description">Short description</label>
        <textarea id="description" name="description">{{ old('description', $intervention->description) }}</textarea>
        <label for="instructions">Student instructions</label>
        <textarea id="instructions" name="instructions">{{ old('instructions', $intervention->instructions) }}</textarea>
        <div class="field-row">
            <div>
                <label for="content_type">Content type</label>
                <select id="content_type" name="content_type" required>
                    @foreach([
                        'breathing' => 'Breathing · Wellbeing Activities',
                        'grounding' => 'Grounding · Wellbeing Activities',
                        'mindfulness' => 'Mindfulness · Wellbeing Activities',
                        'relaxation' => 'Relaxation · Wellbeing Activities',
                        'activity' => 'General activity · Wellbeing Activities',
                        'resource' => 'Resource · Wellbeing Activities',
                        'journaling' => 'Journaling · Positive Engagement',
                        'affirmation' => 'Affirmation · Positive Engagement',
                        'quiz' => 'Light quiz · Positive Engagement',
                        'motivation' => 'Motivation · Positive Engagement',
                        'positive_engagement' => 'Positive activity · Positive Engagement',
                    ] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_type', $intervention->content_type ?? 'activity') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="stress_level">Stress tier (optional)</label>
                <input id="stress_level" name="stress_level" type="text" value="{{ old('stress_level', $intervention->stress_level) }}" placeholder="Leave blank for all tiers">
            </div>
        </div>
        <label for="external_url">Related URL (optional)</label>
        <input id="external_url" name="external_url" type="url" value="{{ old('external_url', $intervention->external_url) }}">
        <label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $intervention->exists ? $intervention->is_active : true))> Active and visible to students</label>
        <div class="actions"><button class="button" type="submit">Save support content</button><a class="button button-secondary" href="{{ route('admin.interventions.index') }}">Cancel</a></div>
    </form>
</main>
@endsection
