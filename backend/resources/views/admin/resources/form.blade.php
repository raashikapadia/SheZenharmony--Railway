@extends('layouts.admin')
@section('title', $resource->exists ? 'Edit helpline resource' : 'Add helpline resource')
@section('body')
<main class="content">
    <a class="backlink" href="{{ route('admin.resources.index') }}">← Helpline Resources</a>
    <h1>{{ $resource->exists ? 'Edit helpline resource' : 'Add helpline resource' }}</h1>
    <p class="muted">Support contacts published here appear in the student Resource tab, where students can call them directly.</p>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <form method="POST" action="{{ $resource->exists ? route('admin.resources.update', $resource) : route('admin.resources.store') }}">
        @csrf
        @if($resource->exists) @method('PUT') @endif

        <div class="form-section">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $resource->name) }}" required placeholder="e.g. National Crisis Helpline">

            <div class="field-row" style="margin-top:16px">
                <div>
                    <label for="organisation">Organisation</label>
                    <input id="organisation" name="organisation" type="text" value="{{ old('organisation', $resource->organisation) }}" placeholder="Who runs it (optional)">
                </div>
                <div>
                    <label for="category">Category</label>
                    <input id="category" name="category" type="text" value="{{ old('category', $resource->category) }}" placeholder="e.g. Crisis or Counselling">
                </div>
            </div>

            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="What this helpline offers, and who it is for.">{{ old('description', $resource->description) }}</textarea>
        </div>

        <div class="form-section">
            <h3>Contact</h3>
            <p class="muted">The phone number is what students tap to call, so enter it exactly as it should be dialled.</p>
            <div class="field-row">
                <div>
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone', $resource->phone) }}" required placeholder="e.g. 1325">
                </div>
                <div>
                    <label for="alternate_phone">Alternate number</label>
                    <input id="alternate_phone" name="alternate_phone" type="text" value="{{ old('alternate_phone', $resource->alternate_phone) }}" placeholder="Optional">
                </div>
            </div>
            <div class="field-row">
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $resource->email) }}" placeholder="Optional">
                </div>
                <div>
                    <label for="website_url">Website</label>
                    <input id="website_url" name="website_url" type="url" value="{{ old('website_url', $resource->website_url) }}" placeholder="https://…">
                </div>
            </div>
            <label for="availability">Availability</label>
            <input id="availability" name="availability" type="text" value="{{ old('availability', $resource->availability) }}" placeholder="e.g. 24 hours, 7 days">
        </div>

        <div class="form-section">
            <h3>Visibility</h3>
            <div class="field-row">
                <div>
                    <label for="position">Display order</label>
                    <input id="position" name="position" type="number" min="0" max="9999" value="{{ old('position', $resource->position ?? 0) }}">
                    <p class="muted" style="margin-top:6px;font-size:.85rem">Lower numbers appear first, including for emergency contacts.</p>
                </div>
            </div>
            <label class="remember"><input name="is_emergency" type="checkbox" value="1" @checked(old('is_emergency', $resource->is_emergency))> Emergency contact</label>
            <label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $resource->exists ? $resource->is_active : true))> Active and visible to students</label>
        </div>

        <div class="actions">
            <button class="button" type="submit">Save resource</button>
            <a class="button button-secondary" href="{{ route('admin.resources.index') }}">Cancel</a>
        </div>
    </form>
</main>
@endsection
