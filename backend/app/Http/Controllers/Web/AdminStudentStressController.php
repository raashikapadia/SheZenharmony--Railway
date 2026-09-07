<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StressAssessment;
use App\Models\StressScoreBand;
use App\Models\StudentIdentity;
use App\Services\AssessmentAnalytics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only admin view of student stress / wellbeing results. Students are
 * identified only by their pseudonymous SheZen ID here — authentication name
 * and email never reach this controller, keeping the privacy boundary intact.
 *
 * Everything is computed live from the current `stress_assessments`,
 * `category_results` and `stress_responses` rows, so it always reflects the
 * users who have actually submitted.
 */
class AdminStudentStressController extends Controller
{
    /** Stress Level Assessment → Overview tab: a compact live snapshot. */
    public function overview(AssessmentAnalytics $analytics): View
    {
        return view('admin.student-stress.overview', ['a' => $analytics->summary()]);
    }

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
            ->with(['scoreBand', 'wellbeingBand', 'stressBand'])
            ->orderByDesc('completed_at')
            ->get();

        return view('admin.student-stress.show', compact('student', 'history'));
    }

    /**
     * A single completed assessment with every answer and the per-category
     * breakdown. Still pseudonymous — only the SheZen ID is shown.
     */
    public function assessment(StressAssessment $assessment): View
    {
        abort_unless($assessment->assessment_status === 'completed', 404);

        $assessment->load([
            'questionnaire',
            'scoreBand',
            'wellbeingBand',
            'stressBand',
            'studentIdentity',
            'categoryResults' => fn ($query) => $query->orderBy('id'),
            'responses' => fn ($query) => $query->orderBy('id'),
        ]);

        return view('admin.student-stress.assessment', compact('assessment'));
    }

    /**
     * Stress Level Assessment → Analytics tab. In-page sub-tabs (Overall,
     * Categories, Questions, Demographics, Stress vs Wellbeing) all render
     * off one shared AssessmentAnalytics dataset — no separate stress
     * analytics computation, no extra navigation items.
     */
    public function analytics(Request $request, AssessmentAnalytics $analytics): View
    {
        return view('admin.student-stress.analytics', [
            'a' => $analytics->summary(),
            'tab' => $request->string('tab')->toString() ?: 'overall',
            'lens' => 'stress',
        ]);
    }
}
