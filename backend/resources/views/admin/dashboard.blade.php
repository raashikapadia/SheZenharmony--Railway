@extends('layouts.admin')
@section('title', 'Dashboard')
@section('body')
<main class="content">
    <div class="page-intro"><div><h2>Platform overview</h2><p class="muted">A privacy-conscious view of the current SheZen Harmony prototype.</p></div></div>
    <section class="cards" aria-label="Platform totals">
        <article class="card"><div class="muted">Registered Students</div><div class="metric">{{ $studentCount }}</div><div class="metric-note">Active local accounts</div></article>
        <article class="card"><div class="muted">Assessments Completed</div><div class="metric">{{ $assessmentCount }}</div><div class="metric-note">Across all questionnaire versions</div></article>
        <article class="card"><div class="muted">Active Questions</div><div class="metric">{{ $questionCount }}</div><div class="metric-note">Available for questionnaires</div></article>
        <article class="card"><div class="muted">Active Support Content</div><div class="metric">{{ $interventionCount }}</div><div class="metric-note">Guided activities and reflections available</div></article>
        <a class="card" href="#" data-coming-soon="Assessments Due" style="text-decoration:none"><div class="muted">Assessments Due</div><div class="metric">—</div><div class="metric-note">Feature still in progress</div></a>
        <a class="card" href="#" data-coming-soon="Intervention Sessions" style="text-decoration:none"><div class="muted">Intervention Sessions</div><div class="metric">—</div><div class="metric-note">Feature still in progress</div></a>
        <a class="card" href="#" data-coming-soon="Notifications"><div class="muted">Notifications Sent</div><div class="metric">—</div><div class="metric-note">Feature still in progress</div></a>
        <a class="card" href="#" data-coming-soon="Deletion Requests"><div class="muted">Deletion Requests</div><div class="metric">—</div><div class="metric-note">Feature still in progress</div></a>
    </section>
</main>
@endsection
