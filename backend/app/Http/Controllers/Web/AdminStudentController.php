<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StudentIdentity;
use Illuminate\Database\Eloquent\Builder;
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
}
