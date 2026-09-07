<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StudentIdentity;
use App\Models\User;
use App\Notifications\AccountHoldNotification;
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
                'user:id,account_status,account_hold_reason,account_held_at',
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
        $student->load('user:id,account_status,account_hold_reason,account_held_at', 'profile');

        return view('admin.students.show', compact('student'));
    }

    public function hold(Request $request, StudentIdentity $student): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $user = $this->studentUser($student);
        abort_unless($user->account_status === 'active', 409, 'Only an active student account can be placed on hold.');
        $user->forceFill([
            'account_status' => 'suspended',
            'account_hold_reason' => trim($data['reason']),
            'account_held_at' => now(),
            'account_held_by_user_id' => $request->user()->id,
        ])->save();

        $user->notify(new AccountHoldNotification);

        return redirect()->route('admin.students.show', $student)
            ->with('status', 'The student account is now on hold and the student has been notified.');
    }

    public function reactivate(StudentIdentity $student): RedirectResponse
    {
        $user = $this->studentUser($student);
        abort_unless($user->account_status === 'suspended', 409, 'Only an account on hold can be reactivated.');
        $user->forceFill([
            'account_status' => 'active',
            'account_hold_reason' => null,
            'account_held_at' => null,
            'account_held_by_user_id' => null,
        ])->save();

        return redirect()->route('admin.students.show', $student)
            ->with('status', 'The student account has been reactivated.');
    }

    private function studentUser(StudentIdentity $student): User
    {
        $user = $student->user()->firstOrFail();
        abort_unless($user->isStudent(), 404);

        return $user;
    }
}
