<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'studentCount' => User::query()->withRole(User::ROLE_STUDENT)->count(),
            'assessmentCount' => StressAssessment::query()
                ->where('assessment_status', 'completed')
                ->count(),
            'questionCount' => StressQuestion::query()->where('is_active', true)->count(),
            'interventionCount' => Intervention::query()->where('is_active', true)->count(),
        ]);
    }
}
