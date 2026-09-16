<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StudentIdentity;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\AccountHoldNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['country', 'gender', 'employment', 'baseline']);
        $students = StudentIdentity::query()
            ->with([
                'user:id,account_status,account_hold_reason,account_held_at',
                'profile:id,student_identity_id,gender,country,employment_status',
            ])
            ->withExists([
                'assessments as has_completed_required_assessment' => fn (Builder $query) => $query
                    ->where('assessment_status', 'completed'),
            ])
            ->when($filters['country'] ?? null, fn ($query, $value) => $query->whereHas('profile', fn ($profile) => $profile->where('country', $value)))
            ->when($filters['gender'] ?? null, fn ($query, $value) => $query->whereHas('profile', fn ($profile) => $profile->where('gender', $value)))
            ->when($filters['employment'] ?? null, fn ($query, $value) => $query->whereHas('profile', fn ($profile) => $profile->where('employment_status', $value)))
            ->when(($filters['baseline'] ?? null) === 'completed', fn ($query) => $query->whereHas('assessments', fn ($assessment) => $assessment->where('assessment_status', 'completed')))
            ->when(($filters['baseline'] ?? null) === 'required', fn ($query) => $query->whereDoesntHave('assessments', fn ($assessment) => $assessment->where('assessment_status', 'completed')))
            ->latest()
            ->paginate(20)->withQueryString();

        $profileValues = fn (string $column) => UserProfile::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

        return view('admin.students.index', compact('students', 'filters') + [
            'countries' => $profileValues('country'), 'genders' => $profileValues('gender'), 'employments' => $profileValues('employment_status'),
        ]);
    }

    public function show(Request $request, StudentIdentity $student): View
    {
        $tab = in_array($request->string('tab')->toString(), ['assessments', 'activities'], true) ? $request->string('tab')->toString() : 'overview';
        $student->load('user:id,account_status,account_hold_reason,account_held_at', 'profile', 'latestAssessment.wellbeingBand', 'latestAssessment.stressBand');
        $assessments = $student->assessments()->where('assessment_status', 'completed')->with(['questionnaire:id,title,version', 'wellbeingBand', 'stressBand'])->latest('completed_at')->get();
        $usages = $student->interventionUsages()->with(['intervention:id,title,content_type', 'assessment.stressBand', 'assessment.wellbeingBand'])->latest('started_at')->get();

        return view('admin.students.show', compact('student', 'tab', 'assessments', 'usages'));
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
