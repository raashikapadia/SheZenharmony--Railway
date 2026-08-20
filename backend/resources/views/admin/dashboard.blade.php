@extends('layouts.admin')

@section('title', 'Dashboard')

@section('body')
<div class="shell">
    <header class="topbar">
        <nav class="nav"><span class="brand">SheZen Harmony Admin</span><a href="{{ route('admin.questionnaires.index') }}">Questionnaires</a><a href="{{ route('admin.questions.index') }}">Questions</a><a href="{{ route('admin.interventions.index') }}">Interventions</a></nav>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="button button-link" type="submit">Sign out</button>
        </form>
    </header>

    <main class="content">
        <h1>Dashboard</h1>
        <p class="muted">A privacy-conscious overview of the current SheZen platform data.</p>
        <section class="cards" aria-label="Platform totals">
            <article class="card"><div class="muted">Students</div><div class="metric">{{ $studentCount }}</div></article>
            <article class="card"><div class="muted">Assessments</div><div class="metric">{{ $assessmentCount }}</div></article>
            <article class="card"><div class="muted">Active questions</div><div class="metric">{{ $questionCount }}</div></article>
            <article class="card"><div class="muted">Active interventions</div><div class="metric">{{ $interventionCount }}</div></article>
        </section>
    </main>
</div>
@endsection
