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

        <h2 style="margin-top:26px">Coping strategy details <span class="muted" style="font-weight:400;font-size:.85rem">— all optional</span></h2>
        <p class="muted" style="font-size:.8rem">Fill these in to turn this into a practical strategy in the student toolkit. Leave them empty for a short tip or affirmation.</p>

        <div class="field-row" style="margin-top:12px">
            <div>
                <label for="title">Short title</label>
                <input id="title" name="title" type="text" maxlength="255" value="{{ old('title', $guidance->title) }}" placeholder="e.g. Box breathing for a racing mind">
            </div>
            <div>
                <label for="duration_minutes">Takes about (minutes)</label>
                <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="600" value="{{ old('duration_minutes', $guidance->duration_minutes) }}" placeholder="e.g. 2">
            </div>
        </div>

        <div style="margin-top:12px">
            <label for="summary">Quick tip <span class="muted">(one or two lines shown before the student opens it)</span></label>
            <textarea id="summary" name="summary" maxlength="500" placeholder="e.g. Pause and focus only on what needs your attention right now.">{{ old('summary', $guidance->summary) }}</textarea>
        </div>

        <div style="margin-top:12px">
            <label for="when_it_helps">When it may help</label>
            <input id="when_it_helps" name="when_it_helps" type="text" maxlength="500" value="{{ old('when_it_helps', $guidance->when_it_helps) }}" placeholder="e.g. When your thoughts feel busy and hard to slow down">
        </div>

        <div style="margin-top:12px">
            <label for="steps">Steps to try <span class="muted">(one step per line)</span></label>
            <textarea id="steps" name="steps" rows="4" maxlength="4000" placeholder="Sit somewhere comfortable&#10;Breathe in for four counts&#10;Hold for four&#10;Breathe out for four">{{ old('steps', $guidance->steps) }}</textarea>
        </div>

        <div style="margin-top:12px">
            <label for="related_intervention_id">Related wellbeing activity <span class="muted">(optional)</span></label>
            <select id="related_intervention_id" name="related_intervention_id">
                <option value="">— none —</option>
                @foreach($activities as $activity)
                    <option value="{{ $activity->id }}" @selected((int) old('related_intervention_id', $guidance->related_intervention_id) === $activity->id)>{{ $activity->title }}</option>
                @endforeach
            </select>
        </div>

        <h2 style="margin-top:26px">Who should see this</h2>
        <p class="muted" style="font-size:.8rem">Choose the check-in outcomes and focus areas this guidance suits. Select nothing to show it to everyone.</p>

        @php
            $selectedBands = collect(old('band_ids', $guidance->recommendations?->pluck('stress_score_band_id')->filter()->all() ?? []))->map(fn ($id) => (int) $id);
            $selectedSections = collect(old('section_ids', $guidance->recommendations?->pluck('questionnaire_section_id')->filter()->all() ?? []))->map(fn ($id) => (int) $id);
        @endphp

        <div class="field-row" style="margin-top:12px">
            <div>
                <label>Check-in result levels</label>
                @forelse($bands as $band)
                    <label style="display:block;font-weight:400;margin:4px 0">
                        <input type="checkbox" name="band_ids[]" value="{{ $band->id }}" @checked($selectedBands->contains($band->id))>
                        {{ $band->label }}
                    </label>
                @empty
                    <p class="muted" style="font-size:.8rem">No score bands configured yet.</p>
                @endforelse
            </div>
            <div>
                <label>Focus areas</label>
                @forelse($sections as $section)
                    <label style="display:block;font-weight:400;margin:4px 0">
                        <input type="checkbox" name="section_ids[]" value="{{ $section->id }}" @checked($selectedSections->contains($section->id))>
                        {{ $section->title }}
                    </label>
                @empty
                    <p class="muted" style="font-size:.8rem">No questionnaire sections configured yet.</p>
                @endforelse
            </div>
        </div>

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
