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
        </div>
        <div class="field-row">
            <div><label>Status</label><span class="badge {{ $student->user?->account_status === 'active' ? 'active' : '' }}">{{ $student->user?->account_status === 'suspended' ? 'On hold' : ($student->user?->account_status ?? 'unavailable') }}</span></div>
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
    <section class="panel" style="margin-top:16px;max-width:800px">
        @if($student->user?->account_status === 'suspended')
            <h2>Account on hold</h2>
            <p class="muted">The student cannot use protected app features until the account is reactivated.</p>
            <div class="field-row">
                <div><label>Reason shown to student</label>{{ $student->user->account_hold_reason }}</div>
                <div><label>Placed on hold</label>{{ $student->user->account_held_at?->format('d M Y, H:i') ?? '—' }}</div>
            </div>
            <form method="POST" action="{{ route('admin.students.reactivate', $student) }}" onsubmit="return confirm('Reactivate this student account?')">
                @csrf @method('PATCH')
                <button class="button" type="submit">Reactivate account</button>
            </form>
        @elseif($student->user?->account_status === 'active')
            <h2>Place account on hold</h2>
            <p class="muted">Use this only for a rules or safety violation. The reason is shown to the student, access is blocked, and an email notice is sent.</p>
            @if($errors->any())
                <ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @endif
            <form method="POST" action="{{ route('admin.students.hold', $student) }}" onsubmit="return confirm('Place this student account on hold?')">
                @csrf @method('PATCH')
                <label for="reason">Reason for hold</label>
                <textarea id="reason" name="reason" required minlength="10" maxlength="1000" placeholder="State the rule or safety concern clearly and respectfully.">{{ old('reason') }}</textarea>
                <div style="margin-top:14px"><button class="button button-danger" type="submit">Place account on hold</button></div>
            </form>
        @else
            <h2>Account actions</h2>
            <p class="muted">This account is not active, so no moderation action is currently available.</p>
        @endif
    </section>
</main>
@endsection
