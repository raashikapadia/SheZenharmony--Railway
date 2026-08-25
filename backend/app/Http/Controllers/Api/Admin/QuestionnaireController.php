<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionnaireRequest;
use App\Http\Requests\Admin\UpdateQuestionnaireRequest;
use App\Http\Resources\Admin\QuestionnaireResource;
use App\Models\Questionnaire;
use App\Models\StressQuestion;
use App\Services\QuestionnaireActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionnaireController extends Controller
{
    private const QUESTIONNAIRE_TYPE = 'stress';

    public function index(Request $request): JsonResponse
    {
        $query = Questionnaire::query()->withCount('questions');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $filter = $request->query('filter', 'all');
        if ($filter === 'active') {
            $query->where('is_active', true);
        } elseif ($filter === 'inactive') {
            $query->where('is_active', false);
        }

        $paginator = $query->latest()->paginate(
            perPage: (int) $request->query('per_page', 20),
        );

        return response()->json([
            'data' => QuestionnaireResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreQuestionnaireRequest $request, QuestionnaireActivationService $activationService): JsonResponse
    {
        $data = $request->validated();

        $questionnaire = DB::transaction(function () use ($data, $request): Questionnaire {
            $nextVersion = (int) Questionnaire::query()->where('type', self::QUESTIONNAIRE_TYPE)->max('version') + 1;

            return Questionnaire::query()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period' => $data['period'] ?? null,
                'type' => self::QUESTIONNAIRE_TYPE,
                'version' => $nextVersion,
                'status' => 'draft',
                'is_active' => false,
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        if (($data['status'] ?? 'draft') === 'published' || ($data['is_active'] ?? false)) {
            $questionnaire = $activationService->activate($questionnaire);
        } elseif (($data['status'] ?? 'draft') === 'archived') {
            $questionnaire->update(['status' => 'archived']);
        }

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => 'Questionnaire created.',
        ], 201);
    }

    public function show(Questionnaire $questionnaire): JsonResponse
    {
        $questionnaire->loadCount('questions')->load([
            'questions' => function ($query): void {
                $query->orderBy('questionnaire_questions.position')
                    ->with(['options' => fn ($options) => $options->orderBy('position')]);
            },
            'scoreBands' => fn ($query) => $query->orderBy('position'),
        ]);

        return response()->json(['data' => new QuestionnaireResource($questionnaire)]);
    }

    public function update(UpdateQuestionnaireRequest $request, Questionnaire $questionnaire, QuestionnaireActivationService $activationService): JsonResponse
    {
        $data = $request->validated();

        // Title/description/period are labelling, not measurement structure —
        // always safe to edit in place, live or not, per the "fix an error
        // without replacing the thing entirely" admin privilege.
        $questionnaire->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'period' => $data['period'] ?? null,
        ]);

        if ($data['status'] === 'published' || ($data['is_active'] ?? false)) {
            $questionnaire = $activationService->activate($questionnaire);
        } else {
            $questionnaire->update(['status' => $data['status'], 'is_active' => false]);
        }

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => 'Questionnaire updated.',
        ]);
    }

    public function destroy(Questionnaire $questionnaire): JsonResponse
    {
        // Questionnaires are never hard-deleted: stress_assessments.questionnaire_id
        // is a restrictOnDelete FK, so historical assessment data would block it
        // anyway. Archiving mirrors the existing web admin panel's behaviour.
        $questionnaire->update(['status' => 'archived', 'is_active' => false]);

        return response()->json(['message' => 'Questionnaire archived.']);
    }

    public function activate(Questionnaire $questionnaire, QuestionnaireActivationService $activationService): JsonResponse
    {
        $questionnaire = $activationService->activate($questionnaire);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => 'Questionnaire activated. Any previously live version of this questionnaire has been archived.',
        ]);
    }

    public function deactivate(Questionnaire $questionnaire): JsonResponse
    {
        $questionnaire->update(['is_active' => false, 'status' => 'archived']);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => 'Questionnaire deactivated.',
        ]);
    }

    /**
     * Clones this questionnaire — including independent copies of its
     * questions, options, and score bands — into a new draft version. Used
     * whenever an admin needs to make a structural change (add/remove a
     * question, change scores) to a questionnaire that already has
     * assessment history: editing that history's source in place is
     * blocked, so this is the sanctioned way to make the change instead.
     * The clone is fully independent — editing it later never affects the
     * original version or any assessment already recorded against it.
     */
    public function createNewVersion(Questionnaire $questionnaire, Request $request): JsonResponse
    {
        $questionnaire->load(['questions.options', 'scoreBands']);

        $clone = DB::transaction(function () use ($questionnaire, $request): Questionnaire {
            $nextVersion = (int) Questionnaire::query()->where('type', $questionnaire->type)->max('version') + 1;

            $clone = Questionnaire::query()->create([
                'title' => $questionnaire->title,
                'description' => $questionnaire->description,
                'period' => $questionnaire->period,
                'type' => $questionnaire->type,
                'version' => $nextVersion,
                'status' => 'draft',
                'is_active' => false,
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach ($questionnaire->questions as $question) {
                $newQuestion = StressQuestion::query()->create([
                    'question_text' => $question->question_text,
                    'dimension' => $question->dimension,
                    'help_text' => $question->help_text,
                    'question_type' => $question->question_type,
                    'position' => $question->position,
                    'is_active' => true,
                    'is_sensitive' => $question->is_sensitive,
                    'created_by_user_id' => $request->user()->id,
                ]);

                foreach ($question->options as $option) {
                    $newQuestion->options()->create([
                        'label' => $option->label,
                        'value' => $option->value,
                        'score' => $option->score,
                        'position' => $option->position,
                        'is_active' => true,
                    ]);
                }

                $clone->questions()->attach($newQuestion->id, [
                    'position' => $question->pivot->position,
                    'is_required' => $question->pivot->is_required,
                ]);
            }

            foreach ($questionnaire->scoreBands as $band) {
                $clone->scoreBands()->create([
                    'code' => $band->code,
                    'label' => $band->label,
                    'min_score' => $band->min_score,
                    'max_score' => $band->max_score,
                    'position' => $band->position,
                    'is_active' => $band->is_active,
                    'created_by_user_id' => $request->user()->id,
                ]);
            }

            return $clone;
        });

        $clone->loadCount('questions')->load([
            'questions' => fn ($query) => $query->orderBy('questionnaire_questions.position')
                ->with(['options' => fn ($options) => $options->orderBy('position')]),
            'scoreBands' => fn ($query) => $query->orderBy('position'),
        ]);

        return response()->json([
            'data' => new QuestionnaireResource($clone),
            'message' => 'New draft version created. Edit it freely, then activate it when ready — the '
                .'original version and its history are untouched.',
        ], 201);
    }
}
