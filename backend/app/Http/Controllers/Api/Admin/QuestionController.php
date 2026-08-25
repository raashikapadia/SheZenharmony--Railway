<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderQuestionsRequest;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Http\Resources\Admin\QuestionResource;
use App\Models\Questionnaire;
use App\Models\StressQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function store(StoreQuestionRequest $request, Questionnaire $questionnaire): JsonResponse
    {
        $data = $request->validated();

        $question = DB::transaction(function () use ($data, $questionnaire, $request): StressQuestion {
            $question = StressQuestion::query()->create([
                'question_text' => $data['question_text'],
                'dimension' => $data['dimension'] ?? null,
                'question_type' => $data['question_type'],
                'position' => $data['position'] ?? 0,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach (array_values($data['options']) as $index => $option) {
                $question->options()->create([
                    'label' => $option['label'],
                    'value' => $option['value'],
                    'score' => $option['score'] ?? null,
                    'position' => $index + 1,
                    'is_active' => true,
                ]);
            }

            $nextPosition = (int) $questionnaire->questions()->max('questionnaire_questions.position') + 1;
            $questionnaire->questions()->attach($question->id, [
                'position' => $nextPosition,
                'is_required' => (bool) ($data['is_required'] ?? true),
            ]);

            return $question;
        });

        $question->load('options')->setRelation(
            'pivot',
            $questionnaire->questions()->whereKey($question->id)->first()->pivot,
        );

        return response()->json([
            'data' => new QuestionResource($question),
            'message' => 'Question added.',
        ], 201);
    }

    public function update(UpdateQuestionRequest $request, Questionnaire $questionnaire, StressQuestion $question): JsonResponse
    {
        $this->ensureBelongsToQuestionnaire($questionnaire, $question);

        $data = $request->validated();

        DB::transaction(function () use ($data, $questionnaire, $question): void {
            $question->fill([
                'question_text' => $data['question_text'],
                'dimension' => $data['dimension'] ?? null,
                'question_type' => $data['question_type'],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
            ])->save();

            $retainedIds = [];
            foreach (array_values($data['options']) as $index => $option) {
                $optionId = $option['id'] ?? null;
                $values = [
                    'label' => $option['label'],
                    'value' => $option['value'],
                    'score' => $option['score'] ?? null,
                    'position' => $index + 1,
                    'is_active' => true,
                ];

                if ($optionId !== null) {
                    $existing = $question->options()->whereKey($optionId)->firstOrFail();
                    $existing->update($values);
                    $retainedIds[] = $existing->id;
                } else {
                    $retainedIds[] = $question->options()->create($values)->id;
                }
            }
            $question->options()->whereNotIn('id', $retainedIds)->update(['is_active' => false]);

            $questionnaire->questions()->updateExistingPivot($question->id, [
                'is_required' => (bool) ($data['is_required'] ?? true),
            ]);
        });

        $question->refresh()->load('options')->setRelation(
            'pivot',
            $questionnaire->questions()->whereKey($question->id)->first()->pivot,
        );

        return response()->json([
            'data' => new QuestionResource($question),
            'message' => 'Question updated.',
        ]);
    }

    public function destroy(Questionnaire $questionnaire, StressQuestion $question): JsonResponse
    {
        $this->ensureBelongsToQuestionnaire($questionnaire, $question);

        return DB::transaction(function () use ($questionnaire, $question): JsonResponse {
            $questionnaire->questions()->detach($question->id);

            if ($question->responses()->exists()) {
                $question->update(['is_active' => false]);

                return response()->json([
                    'message' => 'Question has response history — removed from this questionnaire and deactivated instead of deleted.',
                ]);
            }

            if ($question->questionnaires()->exists()) {
                // Still used by another questionnaire: keep the shared question record.
                return response()->json(['message' => 'Question removed from this questionnaire.']);
            }

            $question->options()->delete();
            $question->delete();

            return response()->json(['message' => 'Question deleted.']);
        });
    }

    public function reorder(ReorderQuestionsRequest $request, Questionnaire $questionnaire): JsonResponse
    {
        $items = collect($request->validated('questions'));
        $attachedIds = $questionnaire->questions()->pluck('stress_questions.id');

        $unknownIds = $items->pluck('id')->diff($attachedIds);
        if ($unknownIds->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more questions do not belong to this questionnaire.',
                'errors' => ['questions' => ["Unknown question id(s): {$unknownIds->join(', ')}"]],
            ], 422);
        }

        DB::transaction(function () use ($questionnaire, $items): void {
            foreach ($items as $item) {
                $questionnaire->questions()->updateExistingPivot($item['id'], ['position' => $item['position']]);
            }
        });

        $questionnaire->load(['questions' => function ($query): void {
            $query->orderBy('questionnaire_questions.position')
                ->with(['options' => fn ($options) => $options->orderBy('position')]);
        }]);

        return response()->json([
            'data' => QuestionResource::collection($questionnaire->questions),
            'message' => 'Questions reordered.',
        ]);
    }

    private function ensureBelongsToQuestionnaire(Questionnaire $questionnaire, StressQuestion $question): void
    {
        abort_unless(
            $questionnaire->questions()->where('stress_questions.id', $question->id)->exists(),
            404,
            'Question not found in this questionnaire.',
        );
    }
}
