@extends('layouts.admin')
@section('title', 'Chat Buddy Preview')
@section('body')
<main class="content"><section class="panel stack-sm"><a class="backlink" href="{{ route('admin.chatbuddy.releases.index') }}">&larr; Chat Buddy Manager</a><h1>Draft preview</h1><p class="muted">This uses the same deterministic matcher as the student API. It does not publish anything.</p><form method="POST" action="{{ route('admin.chatbuddy.releases.preview') }}">@csrf<input type="hidden" name="release_id" value="{{ $release->id }}"><label>Sample message<textarea name="message" required>{{ $sample }}</textarea></label><button class="button" type="submit">Preview match</button></form></section>@if($result)<section class="panel"><h2>Result</h2><p><strong>Reply:</strong> {{ $result['message'] }}</p><p><strong>Safety rule:</strong> {{ $result['is_safety'] ? 'Yes' : 'No' }}</p><p><strong>Fallback:</strong> {{ $result['is_fallback'] ? 'Yes' : 'No' }}</p></section>@endif</main>
@endsection
