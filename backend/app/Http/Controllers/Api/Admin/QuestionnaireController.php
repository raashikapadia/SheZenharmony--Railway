<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionnaireRequest;
use App\Http\Requests\Admin\UpdateQuestionnaireRequest;
use App\Http\Resources\Admin\QuestionnaireResource;
use App\Models\Questionnaire;
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

    public function store(StoreQuestionnaireRequest $request): JsonResponse
    {
        $data = $request->validated();

        $questionnaire = DB::transaction(function () use ($data, $request): Questionnaire {
            $nextVersion = (int) Questionnaire::query()->where('type', self::QUESTIONNAIRE_TYPE)->max('version') + 1;

            return Questionnaire::query()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => self::QUESTIONNAIRE_TYPE,
                'version' => $nextVersion,
                'status' => $data['status'] ?? 'draft',
                'is_active' => (bool) ($data['is_active'] ?? false),
                'created_by_user_id' => $request->user()->id,
            ]);
        });

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

    public function update(UpdateQuestionnaireRequest $request, Questionnaire $questionnaire): JsonResponse
    {
        $data = $request->validated();

        $questionnaire->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

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

    public function activate(Questionnaire $questionnaire): JsonResponse
    {
        $questionnaire->update([
            'is_active' => true,
            'status' => 'published',
            'published_at' => $questionnaire->published_at ?? now(),
        ]);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => 'Questionnaire activated.',
        ]);
    }

    public function deactivate(Questionnaire $questionnaire): JsonResponse
    {
        $questionnaire->update(['is_active' => false]);

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire->fresh()),
            'message' => 'Questionnaire deactivated.',
        ]);
    }
}
