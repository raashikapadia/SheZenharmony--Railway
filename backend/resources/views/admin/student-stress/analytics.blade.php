@extends('layouts.admin')
@section('title', 'Analytics · Assessments')
@section('body')
<main class="content stack">
    @include('admin.student-stress._nav')

    <div>
        <h1 style="margin:0 0 4px">Analytics</h1>
        <p class="lede">Live aggregate figures across every completed assessment — counts and averages only, no student is identified.</p>
    </div>

    @include('admin._analytics', ['routeName' => 'admin.student-stress.analytics'])
</main>
@endsection
