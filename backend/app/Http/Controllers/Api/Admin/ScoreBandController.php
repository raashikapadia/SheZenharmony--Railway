<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScoreBandRequest;
use App\Http\Requests\Admin\UpdateScoreBandRequest;
use App\Http\Resources\Admin\ScoreBandResource;
use App\Models\Questionnaire;
use App\Models\StressScoreBand;
use App\Services\ScaleBandValidator;
use Illuminate\Http\JsonResponse;

class ScoreBandController extends Controller
{
    public function store(StoreScoreBandRequest $request, Questionnaire $questionnaire, ScaleBandValidator $validator): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $validator->validate($questionnaire, $data);

        $band = $questionnaire->scoreBands()->create($data + [
            'position' => $data['position'] ?? ((int) $questionnaire->scoreBands()->max('position') + 1),
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new ScoreBandResource($band),
            'message' => 'Score range added.',
        ], 201);
    }

    public function update(UpdateScoreBandRequest $request, Questionnaire $questionnaire, StressScoreBand $band, ScaleBandValidator $validator): JsonResponse
    {
        $this->ensureBelongsToQuestionnaire($questionnaire, $band);

        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $validator->validate($questionnaire, $data, $band);

        $band->update($data + ['position' => $data['position'] ?? $band->position]);

        return response()->json([
            'data' => new ScoreBandResource($band->fresh()),
            'message' => 'Score range updated.',
        ]);
    }

    public function destroy(Questionnaire $questionnaire, StressScoreBand $band): JsonResponse
    {
        $this->ensureBelongsToQuestionnaire($questionnaire, $band);

        if ($band->assessments()->exists()) {
            $band->update(['is_active' => false]);

            return response()->json([
                'message' => 'This score range has past assessments attached to it and was deactivated instead of deleted.',
            ]);
        }

        $band->delete();

        return response()->json(['message' => 'Score range deleted.']);
    }

    private function ensureBelongsToQuestionnaire(Questionnaire $questionnaire, StressScoreBand $band): void
    {
        abort_unless($band->questionnaire_id === $questionnaire->id, 404, 'Score range not found in this questionnaire.');
    }
}
