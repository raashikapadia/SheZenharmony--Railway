<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAssessmentRequest;
use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\StressScoreBand;
use App\Services\AssessmentSubmissionService;
use App\Services\RecommendedInterventionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssessmentController extends Controller
{
    /**
     * The signed-in student's completed attempts, newest first. Optional
     * `purpose` (registration | library) and `questionnaire_id` filters let
     * the app keep the onboarding baseline apart from the questionnaires the
     * student chose to sit.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'purpose' => ['nullable', Rule::in(Questionnaire::purposes())],
            'questionnaire_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $assessments = $request->user()->studentIdentity()->firstOrFail()->assessments()
            ->where('assessment_status', 'completed')
            ->when($filters['purpose'] ?? null, fn ($query, $purpose) => $query
                ->whereHas('questionnaire', fn ($q) => $q->where('purpose', $purpose)))
            ->when($filters['questionnaire_id'] ?? null, fn ($query, $id) => $query->where('questionnaire_id', $id))
            ->with(['questionnaire', 'scoreBand'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();

        $scoreOutOf = $this->scoreOutOfMany($assessments->pluck('questionnaire_id')->filter()->unique()->all());

        return response()->json([
            'data' => $assessments->map(fn ($assessment) => [
                'id' => $assessment->id,
                'questionnaire_id' => $assessment->questionnaire_id,
                'questionnaire_title' => $assessment->questionnaire?->title,
                'questionnaire_version' => $assessment->questionnaire?->version,
                'purpose' => $assessment->questionnaire?->purpose,
                'total_score' => $assessment->total_score,
                'score_out_of' => $scoreOutOf[$assessment->questionnaire_id] ?? null,
                'percentage' => $assessment->overall_percentage !== null ? (float) $assessment->overall_percentage : null,
                'band' => $this->band($assessment->scoreBand),
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
            'percentage' => $assessment->overall_percentage !== null ? (float) $assessment->overall_percentage : null,
            'band' => $this->band($band),
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
                'questionnaire_version' => $assessment->questionnaire?->version,
                'purpose' => $assessment->questionnaire?->purpose,
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

        $assessment->load(['questionnaire', 'scoreBand', 'responses', 'categoryResults']);
        $band = $assessment->scoreBand;

        return response()->json([
            'data' => [
                'id' => $assessment->id,
                'questionnaire_id' => $assessment->questionnaire_id,
                'questionnaire_title' => $assessment->questionnaire?->title,
                'questionnaire_version' => $assessment->questionnaire?->version,
                'purpose' => $assessment->questionnaire?->purpose,
                'total_score' => $assessment->total_score,
                'score_out_of' => $this->scoreOutOf($assessment->questionnaire_id),
                'percentage' => $assessment->overall_percentage !== null ? (float) $assessment->overall_percentage : null,
                'band' => $this->band($band),
                'completed_at' => $assessment->completed_at?->toISOString(),
                'responses' => $assessment->responses->map(fn ($response) => [
                    'question' => $response->question_text_snapshot,
                    'answer' => $response->option_text_snapshot,
                    'score' => $response->score,
                ])->values(),
                // The per-section outcome exactly as it was stored at
                // submission time — never recomputed from live configuration.
                'sections' => $assessment->categoryResults->map(fn ($category) => [
                    'title' => $category->section_title_snapshot,
                    'raw_score' => (float) $category->raw_score,
                    'max_possible_score' => (float) $category->max_possible_score,
                    'percentage' => (float) $category->percentage,
                    'weight' => (float) $category->category_weight,
                    'weighted_score' => (float) $category->weighted_score,
                ])->values(),
                'recommended_interventions' => $band
                    ? $recommendations->forBand($band)->map($recommendations->payload(...))->values()
                    : [],
            ],
        ]);
    }

    /** @return array{code: string, label: string, description: string|null, message: string|null}|null */
    private function band(?StressScoreBand $band): ?array
    {
        if ($band === null) {
            return null;
        }

        return [
            'code' => $band->code,
            'label' => $band->label,
            'description' => $band->description,
            'message' => $band->harmony_message,
        ];
    }

    private function scoreOutOf(?int $questionnaireId): ?int
    {
        if ($questionnaireId === null) {
            return null;
        }

        return $this->scoreOutOfMany([$questionnaireId])[$questionnaireId] ?? null;
    }

    /**
     * The top of each questionnaire's overall result range, in one query.
     *
     * @param  array<int, int>  $questionnaireIds
     * @return array<int, int>
     */
    private function scoreOutOfMany(array $questionnaireIds): array
    {
        if ($questionnaireIds === []) {
            return [];
        }

        return StressScoreBand::query()
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->where('scope', StressScoreBand::SCOPE_OVERALL)
            ->where('is_active', true)
            ->selectRaw('questionnaire_id, MAX(max_score) as top')
            ->groupBy('questionnaire_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->questionnaire_id => (int) $row->top])
            ->all();
    }
}
