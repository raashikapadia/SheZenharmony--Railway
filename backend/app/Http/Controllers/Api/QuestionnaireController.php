<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Services\QuestionnairePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student-facing questionnaire surface.
 *
 * Registration and the library are deliberately separate endpoints:
 * `registration()` serves the one mandatory baseline the onboarding gate
 * needs, and `available()` lists the questionnaires a student may choose to
 * sit. The baseline never appears in that list, so finishing onboarding and
 * picking something to sit are never the same decision.
 */
class QuestionnaireController extends Controller
{
    public function __construct(private readonly QuestionnairePresenter $presenter) {}

    /**
     * Every live library questionnaire, newest first, with the signed-in
     * student's own attempt history folded in so the app can show "not
     * started" / "last taken" without a second round trip per row.
     */
    public function available(Request $request): JsonResponse
    {
        $questionnaires = Questionnaire::query()
            ->notInTrash()
            ->library()
            ->availableToStudents()
            ->withCount([
                'questions' => fn ($query) => $query->where('stress_questions.is_active', true),
                'sections' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderByDesc('published_at')
            ->orderBy('title')
            ->get();

        $attempts = $this->attemptStats($request, $questionnaires->pluck('id')->all());

        return response()->json([
            'data' => $questionnaires
                ->map(fn (Questionnaire $questionnaire) => $this->presenter->summary(
                    $questionnaire,
                    $attempts[$questionnaire->id] ?? null,
                ))
                ->values(),
        ]);
    }

    /**
     * The mandatory post-registration baseline. 404 carries the reason so
     * the app can explain itself rather than showing an empty screen.
     */
    public function registration(): JsonResponse
    {
        $questionnaire = $this->loadForTaking(
            Questionnaire::query()->notInTrash()->registration()->availableToStudents()
        );

        if (! $questionnaire) {
            return response()->json(['message' => $this->unavailableMessage(
                Questionnaire::query()->notInTrash()->registration(),
                'Your first check-in is not available right now. Please check back later.',
            )], 404);
        }

        return response()->json(['data' => $this->presenter->forTaking($questionnaire)]);
    }

    /**
     * One questionnaire, ready to answer. Scoped to what is genuinely open
     * to students, so an id for a draft, a scheduled version or a trashed
     * row is a 404 rather than an unanswerable form.
     */
    public function show(Questionnaire $questionnaire): JsonResponse
    {
        abort_unless($questionnaire->trashed_at === null && $questionnaire->isAvailable(), 404);

        $loaded = $this->loadForTaking(
            Questionnaire::query()->whereKey($questionnaire->getKey())
        );

        abort_unless($loaded !== null, 404);

        return response()->json(['data' => $this->presenter->forTaking($loaded)]);
    }

    /**
     * Kept so an older build of the app keeps working: it has always meant
     * "the questionnaire the onboarding gate wants", which is now the
     * registration baseline.
     *
     * @deprecated Use registration() or available() instead.
     */
    public function active(): JsonResponse
    {
        return $this->registration();
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Questionnaire>  $query */
    private function loadForTaking($query): ?Questionnaire
    {
        return $query
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
    }

    /**
     * Why nothing is available: a scheduled go-live date is worth telling a
     * student about, anything else is not.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Questionnaire>  $query
     */
    private function unavailableMessage($query, string $fallback): string
    {
        $scheduled = (clone $query)
            ->where('status', 'published')
            ->where('is_active', true)
            ->where('published_at', '>', now())
            ->orderBy('published_at')
            ->first();

        if (! $scheduled) {
            return $fallback;
        }

        return 'The next check-in opens on '.$scheduled->published_at->format('j F Y')
            .' at '.$scheduled->published_at->format('g:ia').'. Please come back then.';
    }

    /**
     * Completed-attempt count and most recent completion per questionnaire
     * for the signed-in student, in one grouped query.
     *
     * @param  array<int, int>  $questionnaireIds
     * @return array<int, array{attempts: int, last_completed_at: string|null}>
     */
    private function attemptStats(Request $request, array $questionnaireIds): array
    {
        $identity = $request->user()?->studentIdentity()->first();
        if (! $identity || $questionnaireIds === []) {
            return [];
        }

        return StressAssessment::query()
            ->where('student_identity_id', $identity->id)
            ->where('assessment_status', 'completed')
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->selectRaw('questionnaire_id, COUNT(*) as attempts, MAX(completed_at) as last_completed_at')
            ->groupBy('questionnaire_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->questionnaire_id => [
                'attempts' => (int) $row->attempts,
                'last_completed_at' => $row->last_completed_at,
            ]])
            ->all();
    }
}
