<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Services\QuestionnairePresenter;
use Illuminate\Http\JsonResponse;

/**
 * The student-facing questionnaire surface — one endpoint, one workflow.
 *
 * The app's Stress Level section asks for the live questionnaire and renders
 * whatever configuration comes back. Exactly one questionnaire is live at a
 * time (see {@see \App\Services\QuestionnaireActivationService}), so nothing
 * here names a questionnaire, a version, a scoring rule or a question count:
 * changing any of those in the admin changes what students see, with no code
 * change.
 */
class QuestionnaireController extends Controller
{
    public function __construct(private readonly QuestionnairePresenter $presenter) {}

    /**
     * The live questionnaire, ready to answer. A 404 carries the reason so
     * the app can explain itself rather than showing an empty screen.
     */
    public function active(): JsonResponse
    {
        $questionnaire = Questionnaire::query()
            ->notInTrash()
            ->availableToStudents()
            ->with([
                'sections' => fn ($sections) => $sections->where('is_active', true)
                    ->orderBy('position')->orderBy('id'),
                'questions' => fn ($questions) => $questions
                    ->where('stress_questions.is_active', true)
                    ->orderBy('questionnaire_questions.position')
                    ->with(['options' => fn ($options) => $options->where('is_active', true)->orderBy('position')]),
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('version')
            ->first();

        if (! $questionnaire) {
            return response()->json(['message' => $this->unavailableMessage()], 404);
        }

        return response()->json(['data' => $this->presenter->forTaking($questionnaire)]);
    }

    /**
     * Why nothing is available: a scheduled go-live date is worth telling a
     * student about, anything else is not.
     */
    private function unavailableMessage(): string
    {
        $scheduled = Questionnaire::query()
            ->notInTrash()
            ->where('status', 'published')
            ->where('is_active', true)
            ->where('published_at', '>', now())
            ->orderBy('published_at')
            ->first();

        if (! $scheduled) {
            return 'Your check-in is not available right now. Please check back later.';
        }

        return 'The next check-in opens on '.$scheduled->published_at->format('j F Y')
            .' at '.$scheduled->published_at->format('g:ia').'. Please come back then.';
    }
}
