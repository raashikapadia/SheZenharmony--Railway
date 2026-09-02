<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StressAssessment;
use App\Models\StressScoreBand;
use App\Models\StudentIdentity;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only admin view of student stress results. Students are identified
 * only by their pseudonymous SheZen ID here — authentication name and email
 * never reach this controller, keeping the privacy boundary intact.
 */
class AdminStudentStressController extends Controller
{
    public function index(Request $request): View
    {
        $bandFilter = $request->string('band')->toString();

        $students = StudentIdentity::query()
            ->with(['latestAssessment.scoreBand'])
            ->whereHas('assessments', fn ($query) => $query->where('assessment_status', 'completed'))
            ->when($bandFilter !== '', fn ($query) => $query->whereHas(
                'latestAssessment.scoreBand',
                fn ($band) => $band->where('code', $bandFilter),
            ))
            ->addSelect(['latest_completed_at' => StressAssessment::query()
                ->selectRaw('max(completed_at)')
                ->whereColumn('student_identity_id', 'student_identities.id')
                ->where('assessment_status', 'completed')])
            ->orderByDesc('latest_completed_at')
            ->paginate(20)
            ->withQueryString();

        $bands = StressScoreBand::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get(['code', 'label'])
            ->unique('code')
            ->values();

        return view('admin.student-stress.index', compact('students', 'bands', 'bandFilter'));
    }

    public function show(StudentIdentity $student): View
    {
        $student->load('latestAssessment.scoreBand');

        $history = $student->assessments()
            ->where('assessment_status', 'completed')
            ->with('scoreBand')
            ->orderByDesc('completed_at')
            ->get();

        return view('admin.student-stress.show', compact('student', 'history'));
    }
}
