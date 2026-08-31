<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StudentIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStudentController extends Controller
{
    public function index(): View
    {
        $students = StudentIdentity::query()
            ->with([
                'user:id,account_status',
                'profile:id,student_identity_id,gender,country,employment_status',
            ])
            ->withExists([
                'assessments as has_completed_required_assessment' => fn (Builder $query) => $query
                    ->where('assessment_status', 'completed'),
            ])
            ->latest()
            ->paginate(20);

        return view('admin.students.index', compact('students'));
    }

    public function show(StudentIdentity $student): View
    {
        $student->load('user:id,account_status', 'profile');

        return view('admin.students.show', compact('student'));
    }

    public function edit(StudentIdentity $student): View
    {
        $student->load('profile');

        return view('admin.students.edit', compact('student'));
    }

    public function update(Request $request, StudentIdentity $student): RedirectResponse
    {
        $data = $request->validate([
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'country' => ['nullable', 'string', 'max:100'],
            'year_of_study' => ['nullable', 'string', 'max:30'],
            'employment_status' => ['nullable', 'string', 'max:100'],
            'relationship_status' => ['nullable', 'string', 'max:100'],
            'has_children' => ['nullable', 'boolean'],
            'living_situation' => ['nullable', 'string', 'max:150'],
        ], [
            'date_of_birth.before' => 'Date of birth cannot be in the future.',
        ]);

        $student->profile()->updateOrCreate([], $data);

        return redirect()->route('admin.students.show', $student)
            ->with('status', 'Student profile updated successfully.');
    }
}
