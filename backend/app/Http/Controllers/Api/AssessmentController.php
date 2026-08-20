<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAssessmentRequest;
use App\Services\AssessmentSubmissionService;
use Illuminate\Http\JsonResponse;

class AssessmentController extends Controller
{
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
