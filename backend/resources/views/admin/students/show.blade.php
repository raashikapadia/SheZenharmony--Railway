@extends('layouts.admin')
@section('title', 'Student Profile')
@section('body')
<main class="content">
    <a href="{{ route('admin.students.index') }}">← Registered Students</a>
    @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
    <section class="panel" style="margin-top:16px">
        <div class="page-intro">
            <div>
                <h2 style="font-family:ui-monospace,monospace">{{ $student->displayId() }}</h2>
                <p class="muted">Pseudonymous student profile. Authentication name and email are not shown here.</p>
            </div>
            <div class="actions">
                <a class="button" href="{{ route('admin.students.edit', $student) }}">Edit User</a>
            </div>
        </div>
        <div class="field-row">
            <div><label>Status</label><span class="badge {{ $student->user?->account_status === 'active' ? 'active' : '' }}">{{ $student->user?->account_status ?? 'unavailable' }}</span></div>
            <div><label>Date of Birth</label>{{ $student->profile?->date_of_birth?->format('d M Y') ?? '—' }}</div>
            <div><label>Age</label>{{ $student->profile?->age ?? '—' }}</div>
            <div><label>Country</label>{{ $student->profile?->country ?? '—' }}</div>
        </div>
        <div class="field-row">
            <div><label>Year of Study</label>{{ $student->profile?->year_of_study ?? '—' }}</div>
            <div><label>Working</label>{{ $student->profile?->employment_status ?? '—' }}</div>
            <div><label>Relationship Status</label>{{ $student->profile?->relationship_status ?? '—' }}</div>
            <div><label>Children</label>{{ $student->profile?->has_children === null ? '—' : ($student->profile->has_children ? 'Yes' : 'No') }}</div>
        </div>
        <div class="field-row">
            <div><label>Living Arrangement</label>{{ $student->profile?->living_situation ?? '—' }}</div>
            <div><label>Registered</label>{{ $student->created_at->format('d M Y') }}</div>
        </div>
    </section>
</main>
@endsection
