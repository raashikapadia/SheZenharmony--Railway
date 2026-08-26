<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAssessmentRequest;
use App\Services\AssessmentSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assessments = $request->user()->studentIdentity()->firstOrFail()->assessments()
            ->where('assessment_status', 'completed')
            ->with(['questionnaire', 'scoreBand'])
            ->orderByDesc('completed_at')
            ->get();

        return response()->json([
            'data' => $assessments->map(fn ($assessment) => [
                'id' => $assessment->id,
                'questionnaire_id' => $assessment->questionnaire_id,
                'questionnaire_title' => $assessment->questionnaire?->title,
                'total_score' => $assessment->total_score,
                'band' => $assessment->scoreBand ? [
                    'code' => $assessment->scoreBand->code,
                    'label' => $assessment->scoreBand->label,
                ] : null,
                'completed_at' => $assessment->completed_at?->toISOString(),
            ])->values(),
        ]);
    }

    public function store(SubmitAssessmentRequest $request, AssessmentSubmissionService $submissionService): JsonResponse
    {
        $data = $request->validated();
        $result = $submissionService->submit(
            $request->user(),
            (int) $data['questionnaire_id'],
            $data['answers'],
        );

        return response()->json([
            'assessment' => [
                'id' => $result['assessment']->id,
                'questionnaire_id' => $result['assessment']->questionnaire_id,
                'completed_at' => $result['assessment']->completed_at?->toISOString(),
            ],
            'result' => [
                'total_score' => $result['assessment']->total_score,
                'band' => [
                    'code' => $result['score_band']->code,
                    'label' => $result['score_band']->label,
                ],
            ],
        ], 201);
    }
}
