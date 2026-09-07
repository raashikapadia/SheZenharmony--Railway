@extends('layouts.admin')
@section('title', $title)
@section('body')
<main class="content">
    <section class="panel stack-sm">
        <span class="badge">Coming Soon</span>
        <h1>{{ $title }}</h1>
        <p class="lede">{{ $description }}</p>
    </section>
</main>
@endsection
