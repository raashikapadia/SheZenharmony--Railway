@extends('layouts.admin')
@section('title', 'Result levels · '.$questionnaire->title)
@section('body')
<main class="content stack questionnaire-creation-step">
    <a class="backlink" href="{{ route('admin.questionnaires.scoring', $questionnaire) }}">← Back to Scoring</a>

    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif

    <div>
        <div class="eyebrow">Step 4 of 5 · Result levels &amp; recommendations</div>
        <h1 style="margin:0 0 4px">Result levels</h1>
        <p class="lede">Turn a score into a result: name each level, say what it means, and choose the support to recommend. Nothing here is fixed — use two levels or ten, named however suits this questionnaire.</p>
    </div>

    @include('admin.questionnaires._creation_progress', ['questionnaire' => $questionnaire, 'review' => $review, 'step' => 4])

    @include('admin.questionnaires._ranges_editor')

    @if($stressBands->isNotEmpty())
        <section class="panel">
            <div class="panel-head"><h3 style="margin:0">Stress indicator ranges <span class="muted">(0–100, optional secondary indicator)</span></h3></div>
            @include('admin.questionnaires._band_table', ['bands' => $stressBands, 'ceiling' => 100])
            <p class="muted" style="margin:10px 0 0;font-size:.85rem">Only used when questions are marked as counting toward the stress indicator. Edit these under the questionnaire's full editor.</p>
        </section>
    @endif
</main>
@endsection
