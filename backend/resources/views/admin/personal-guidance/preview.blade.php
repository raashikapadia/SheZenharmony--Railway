@extends('layouts.admin')
@section('title', 'Preview guidance')
@section('body')
@php
    $typeLabels = ['affirmation' => 'Affirmation', 'quote' => 'Motivational quote', 'guidance' => 'Tip'];
@endphp
<main class="content">
    <a class="backlink" href="{{ route('admin.personal-guidance.index') }}">← Personal Guidance</a>
    <h1>Preview</h1>
    <p class="muted">A rough approximation of how this looks in the app — not pixel-perfect, but close enough to check wording and length before publishing.</p>

    <div style="max-width:380px;margin-top:18px">
        <div style="background:#FFFDFB;border:1px solid #EEE2EC;border-radius:22px;padding:22px;box-shadow:0 10px 30px rgba(107,63,122,.08)">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <span style="font-size:11px;font-weight:800;letter-spacing:1.3px;color:#6B3F7A;text-transform:uppercase">{{ $typeLabels[$guidance->type] ?? ucfirst($guidance->type) }}</span>
                @if($guidance->categoryName())
                    <span style="font-size:11.5px;font-weight:700;color:#A64C6E;background:#FDEEF2;border-radius:999px;padding:4px 10px">{{ $guidance->categoryName() }}</span>
                @endif
            </div>

            @if($guidance->title)
                <div style="font-size:17px;font-weight:700;color:#231B26;margin-bottom:6px">{{ $guidance->title }}</div>
            @endif

            <div style="font-size:15px;color:#231B26;line-height:1.5;margin-bottom:10px">
                {{ $guidance->summary ?: $guidance->content }}
            </div>

            @if($guidance->attribution())
                <div style="font-size:13px;color:#6B5A6E;font-style:italic">— {{ $guidance->attribution() }}</div>
            @endif

            @if($guidance->when_it_helps)
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid #EEE2EC;font-size:13px;color:#6B5A6E"><strong style="color:#231B26">When it may help:</strong> {{ $guidance->when_it_helps }}</div>
            @endif

            @if(!empty($guidance->stepList()))
                <div style="margin-top:12px;font-size:13.5px;color:#231B26">
                    <strong>How to try it</strong>
                    <ol style="margin:6px 0 0;padding-left:20px">
                        @foreach($guidance->stepList() as $step)
                            <li style="margin-bottom:4px">{{ $step }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if($guidance->duration_minutes)
                <div style="margin-top:10px;font-size:12.5px;color:#6B5A6E">⏱ Takes about {{ $guidance->duration_minutes }} minute(s)</div>
            @endif

            @if($guidance->relatedIntervention)
                <div style="margin-top:10px;font-size:12.5px;color:#6B5A6E">🔗 Goes with: {{ $guidance->relatedIntervention->title }}</div>
            @endif
        </div>
        <p class="muted" style="font-size:.8rem;margin-top:10px">
            Status: <strong>{{ ucfirst($guidance->status) }}</strong>
            @if(! $guidance->isVisible()) — not currently visible to students. @endif
        </p>
    </div>

    <div class="actions" style="margin-top:20px">
        <a class="button" href="{{ route('admin.personal-guidance.edit', $guidance) }}">Edit</a>
        <a class="button button-secondary" href="{{ route('admin.personal-guidance.index') }}">Back to list</a>
    </div>
</main>
@endsection
