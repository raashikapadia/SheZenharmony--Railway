@extends('layouts.admin')
@section('title', 'Edit Student Profile')
@section('body')
<main class="content">
    <a href="{{ route('admin.students.show', $student) }}">← {{ $student->displayId() }}</a>
    <h1 style="font-family:ui-monospace,monospace;font-size:1.4rem">Edit Student Profile</h1>
    @if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <form method="POST" action="{{ route('admin.students.update', $student) }}">
        @csrf
        @method('PUT')
        @php($profile = $student->profile)
        <div class="field-row">
            <div>
                <label for="date_of_birth">Date of Birth</label>
                <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $profile?->date_of_birth?->toDateString()) }}">
            </div>
            <div>
                <label>Age</label>
                <input type="text" value="{{ $profile?->age ?? '—' }}" disabled>
            </div>
            <div>
                <label for="country">Country</label>
                <input id="country" name="country" type="text" value="{{ old('country', $profile?->country) }}">
            </div>
        </div>
        <div class="field-row">
            <div>
                <label for="year_of_study">Year of Study</label>
                @php($yearOfStudy = old('year_of_study', $profile?->year_of_study))
                <select id="year_of_study" name="year_of_study">
                    <option value="">— Select —</option>
                    @foreach(['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Postgraduate', 'Other'] as $option)
                        <option value="{{ $option }}" @selected($yearOfStudy === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="employment_status">Working</label>
                @php($employmentStatus = old('employment_status', $profile?->employment_status))
                <select id="employment_status" name="employment_status">
                    <option value="">— Select —</option>
                    @foreach(['Not employed', 'Part-time', 'Full-time', 'Prefer not to say'] as $option)
                        <option value="{{ $option }}" @selected($employmentStatus === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="relationship_status">Relationship Status</label>
                @php($relationshipStatus = old('relationship_status', $profile?->relationship_status))
                <select id="relationship_status" name="relationship_status">
                    <option value="">— Select —</option>
                    @foreach(['Single', 'Partnered', 'Married', 'Divorced', 'Prefer not to say'] as $option)
                        <option value="{{ $option }}" @selected($relationshipStatus === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="field-row">
            <div>
                <label>Children</label>
                @php($hasChildren = old('has_children', $profile?->has_children === null ? null : ($profile->has_children ? '1' : '0')))
                <label class="remember"><input type="radio" name="has_children" value="1" @checked($hasChildren === '1')> Yes</label>
                <label class="remember"><input type="radio" name="has_children" value="0" @checked($hasChildren === '0')> No</label>
            </div>
            <div>
                <label for="living_situation">Living Arrangement</label>
                @php($livingSituation = old('living_situation', $profile?->living_situation))
                <select id="living_situation" name="living_situation">
                    <option value="">— Select —</option>
                    @foreach(['Living alone', 'With family', 'Campus housing', 'Shared housing', 'Other'] as $option)
                        <option value="{{ $option }}" @selected($livingSituation === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="actions" style="margin-top:1.5rem">
            <button class="button" type="submit">Save Changes</button>
            <a class="button button-secondary" href="{{ route('admin.students.show', $student) }}">Cancel</a>
        </div>
    </form>
</main>
@endsection
