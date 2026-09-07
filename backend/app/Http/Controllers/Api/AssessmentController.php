<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAssessmentRequest;
use App\Models\StressAssessment;
use App\Models\StressScoreBand;
use App\Services\AssessmentSubmissionService;
use App\Services\RecommendedInterventionService;
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

    public function store(
        SubmitAssessmentRequest $request,
        AssessmentSubmissionService $submissionService,
        RecommendedInterventionService $recommendations,
    ): JsonResponse {
        $data = $request->validated();
        $result = $submissionService->submit(
            $request->user(),
            (int) $data['questionnaire_id'],
            $data['answers'],
        );

        $assessment = $result['assessment'];
        $band = $result['score_band'];
        $breakdown = $result['scored']['breakdown'] ?? null;

        $resultPayload = [
            'total_score' => $assessment->total_score,
            'score_out_of' => $this->scoreOutOf($assessment->questionnaire_id),
            'band' => [
                'code' => $band->code,
                'label' => $band->label,
            ],
        ];

        // Only present for questionnaires configured with weighted sections;
        // flat questionnaires keep the exact original response shape.
        if ($breakdown !== null) {
            $resultPayload['breakdown'] = $breakdown;
        }

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'questionnaire_id' => $assessment->questionnaire_id,
                'completed_at' => $assessment->completed_at?->toISOString(),
            ],
            'result' => $resultPayload,
            'recommended_interventions' => $recommendations->forBand($band)
                ->map($recommendations->payload(...))
                ->values(),
        ], 201);
    }

    public function show(
        Request $request,
        StressAssessment $assessment,
        RecommendedInterventionService $recommendations,
    ): JsonResponse {
        $identityId = $request->user()->studentIdentity()->firstOrFail()->id;

        // A 404 (not 403) keeps another student's assessment ids unconfirmable.
        abort_unless(
            $assessment->student_identity_id === $identityId && $assessment->assessment_status === 'completed',
            404,
        );

        $assessment->load(['questionnaire', 'scoreBand', 'responses']);
        $band = $assessment->scoreBand;

        return response()->json([
            'data' => [
                'id' => $assessment->id,
                'questionnaire_title' => $assessment->questionnaire?->title,
                'total_score' => $assessment->total_score,
                'score_out_of' => $this->scoreOutOf($assessment->questionnaire_id),
                'band' => $band ? ['code' => $band->code, 'label' => $band->label] : null,
                'completed_at' => $assessment->completed_at?->toISOString(),
                'responses' => $assessment->responses->map(fn ($response) => [
                    'question' => $response->question_text_snapshot,
                    'answer' => $response->option_text_snapshot,
                    'score' => $response->score,
                ])->values(),
                'recommended_interventions' => $band
                    ? $recommendations->forBand($band)->map($recommendations->payload(...))->values()
                    : [],
            ],
        ]);
    }

    private function scoreOutOf(?int $questionnaireId): ?int
    {
        if ($questionnaireId === null) {
            return null;
        }

        $max = StressScoreBand::query()
            ->where('questionnaire_id', $questionnaireId)
            ->where('scope', StressScoreBand::SCOPE_OVERALL)
            ->where('is_active', true)
            ->max('max_score');

        return $max === null ? null : (int) $max;
    }
}
