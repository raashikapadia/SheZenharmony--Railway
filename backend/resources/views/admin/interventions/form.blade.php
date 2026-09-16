@extends('layouts.admin')
@section('title', ($intervention->exists ? 'Edit ' : 'Add ').strtolower($configuration['singular']))
@section('body')
<main class="content">
    <a class="backlink" href="{{ route($configuration['route'].'.index') }}">← {{ $configuration['title'] }}</a>
    <h1>{{ $intervention->exists ? 'Edit '.$configuration['singular'] : 'Add '.strtolower($configuration['singular']) }}</h1>
    <p class="muted">{{ $configuration['description'] }}</p>
    @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $intervention->exists ? route($configuration['route'].'.update', $intervention) : route($configuration['route'].'.store') }}">
        @csrf
        @if($intervention->exists) @method('PUT') @endif
        <label for="title">{{ $configuration['route'] === 'admin.positive-engagement.games' ? 'Game name' : 'Title' }}</label>
        <input id="title" name="title" type="text" value="{{ old('title', $intervention->title) }}" required>
        <label for="description">{{ $configuration['route'] === 'admin.positive-engagement.games' ? 'What students see' : 'Short description' }}</label>
        <textarea id="description" name="description">{{ old('description', $intervention->description) }}</textarea>
        <label for="instructions">{{ $configuration['route'] === 'admin.positive-engagement.games' ? 'How students interact' : 'Student instructions' }}</label>
        <textarea id="instructions" name="instructions">{{ old('instructions', $intervention->instructions) }}</textarea>
        @if($configuration['route'] !== 'admin.positive-engagement.games')
        <div class="field-row">
            <div>
                <label for="content_type">Content type</label>
                <select id="content_type" name="content_type" required>
                    @foreach($configuration['contentTypes'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_type', $intervention->content_type ?? array_key_first($configuration['contentTypes'])) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="stress_level">Legacy stress tier (optional)</label>
                <input id="stress_level" name="stress_level" type="text" value="{{ old('stress_level', $intervention->stress_level) }}" placeholder="Superseded by Recommended stress levels below">
            </div>
        </div>
        <label for="external_url">Related URL (optional)</label>
        <input id="external_url" name="external_url" type="url" value="{{ old('external_url', $intervention->external_url) }}">
        @else
            <input type="hidden" name="content_type" value="positive_engagement">
        @endif

        @if($configuration['route'] !== 'admin.positive-engagement.games')
        @php($selected = collect(old('recommended_band_ids', $selectedBandIds))->map(fn ($id) => (int) $id)->all())
        <fieldset style="margin:14px 0;border:1px solid var(--line,#dfe7e6);border-radius:12px;padding:12px 14px">
            <legend style="padding:0 6px">Recommended stress levels</legend>
            <label class="remember"><input name="all_levels" type="checkbox" value="1" @checked(old('all_levels', empty($selectedBandIds)) && empty($selected))> All levels (show to every student)</label>
            @forelse($bands as $band)
                <label class="remember"><input name="recommended_band_ids[]" type="checkbox" value="{{ $band->id }}" @checked(in_array($band->id, $selected, true))>
                    {{ $band->questionnaire?->title ? $band->questionnaire->title.' — ' : '' }}{{ $band->label }} ({{ $band->min_score }}–{{ $band->max_score }})</label>
            @empty
                <p class="muted">No active score bands configured yet. This item will show to all students until bands exist.</p>
            @endforelse
            <p class="muted" style="margin-top:6px">Tick “All levels” to clear specific bands. Only published items reach students.</p>
        </fieldset>
        @endif

        <label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $intervention->exists ? $intervention->is_active : true))> {{ $configuration['route'] === 'admin.positive-engagement.games' ? 'Published (visible in the student app)' : 'Published (visible to students)' }}</label>
        <div class="actions"><button class="button" type="submit">Save {{ strtolower($configuration['singular']) }}</button><a class="button button-secondary" href="{{ route($configuration['route'].'.index') }}">Cancel</a></div>
    </form>
</main>
@endsection
