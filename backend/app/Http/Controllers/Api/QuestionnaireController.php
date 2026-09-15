<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use Illuminate\Http\JsonResponse;

class QuestionnaireController extends Controller
{
    public function active(): JsonResponse
    {
        // Same availability rule as submission: published, active, and past
        // its go-live time — so the app never sees a questionnaire it cannot
        // yet answer.
        $questionnaire = Questionnaire::query()
            ->where('status', 'published')->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->with([
                'sections' => fn ($query) => $query->where('is_active', true)->orderBy('position')->orderBy('id'),
                'questions' => function ($query): void {
                    $query->where('stress_questions.is_active', true)
                        ->orderBy('questionnaire_questions.position')
                        ->with(['options' => fn ($options) => $options->where('is_active', true)->orderBy('position')]);
                },
            ])
            ->orderByDesc('published_at')->orderByDesc('version')->first();

        if (! $questionnaire) {
            $scheduled = Questionnaire::query()
                ->where('status', 'published')->where('is_active', true)
                ->where('published_at', '>', now())
                ->orderBy('published_at')
                ->first();

            return response()->json([
                'message' => $scheduled
                    ? 'The next check-in opens on '.$scheduled->published_at->format('j F Y').' at '.$scheduled->published_at->format('g:ia').'. Please come back then.'
                    : 'There is no check-in available right now. Please check back later.',
            ], 404);
        }

        return response()->json(['data' => [
            'id' => $questionnaire->id,
            'title' => $questionnaire->title,
            'description' => $questionnaire->description,
            'type' => $questionnaire->type,
            'version' => $questionnaire->version,
            'sections' => $questionnaire->sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'position' => $section->position,
            ])->values(),
            'questions' => $questionnaire->questions->map(fn ($question) => [
                'id' => $question->id,
                'text' => $question->question_text,
                'required' => (bool) $question->pivot->is_required,
                'type' => $question->question_type,
                'position' => $question->pivot->position,
                'section_id' => $question->pivot->questionnaire_section_id,
                'options' => $question->options->map(fn ($option) => [
                    'id' => $option->id, 'label' => $option->label, 'value' => $option->value,
                ])->values(),
            ])->values(),
        ]]);
    }
}
