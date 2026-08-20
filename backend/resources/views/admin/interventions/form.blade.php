@extends('layouts.admin')
@section('title', $intervention->exists ? 'Edit intervention' : 'Add intervention')
@section('body')
<main class="content"><a href="{{ route('admin.interventions.index') }}">← Interventions</a><h1>{{ $intervention->exists ? 'Edit intervention' : 'Add intervention' }}</h1>
@if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<form method="POST" action="{{ $intervention->exists ? route('admin.interventions.update', $intervention) : route('admin.interventions.store') }}">@csrf @if($intervention->exists) @method('PUT') @endif
<label for="title">Title</label><input id="title" name="title" type="text" value="{{ old('title', $intervention->title) }}" required><label for="description">Description</label><textarea id="description" name="description">{{ old('description', $intervention->description) }}</textarea>
<div class="field-row"><div><label for="content_type">Content type</label><input id="content_type" name="content_type" type="text" value="{{ old('content_type', $intervention->content_type ?? 'activity') }}" required></div><div><label for="stress_level">Stress level</label><input id="stress_level" name="stress_level" type="text" value="{{ old('stress_level', $intervention->stress_level) }}"></div></div><label for="external_url">External URL</label><input id="external_url" name="external_url" type="url" value="{{ old('external_url', $intervention->external_url) }}"><label class="remember"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', $intervention->exists ? $intervention->is_active : true))> Active</label>
<div class="actions"><button class="button" type="submit">Save intervention</button><a class="button button-secondary" href="{{ route('admin.interventions.index') }}">Cancel</a></div></form></main>
@endsection
