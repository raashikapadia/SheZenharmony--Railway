<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use Illuminate\Http\JsonResponse;

class QuestionnaireController extends Controller
{
    public function active(): JsonResponse
    {
        $questionnaire = Questionnaire::query()
            ->where('status', 'published')->where('is_active', true)
            ->with(['questions' => function ($query): void {
                $query->where('stress_questions.is_active', true)
                    ->orderBy('questionnaire_questions.position')
                    ->with(['options' => fn ($options) => $options->where('is_active', true)->orderBy('position')]);
            }])
            ->orderByDesc('published_at')->orderByDesc('version')->firstOrFail();

        return response()->json(['data' => [
            'id' => $questionnaire->id,
            'title' => $questionnaire->title,
            'description' => $questionnaire->description,
            'type' => $questionnaire->type,
            'version' => $questionnaire->version,
            'questions' => $questionnaire->questions->map(fn ($question) => [
                'id' => $question->id,
                'text' => $question->question_text,
                'required' => (bool) $question->pivot->is_required,
                'position' => $question->pivot->position,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id, 'label' => $option->label, 'value' => $option->value,
                ])->values(),
            ])->values(),
        ]]);
    }
}
