<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Quiz::active()->with('questions')->orderBy('name')->get()->map(
                fn (Quiz $quiz): array => $this->quizPayload($quiz),
            ),
        ]);
    }

    public function show(Quiz $quiz): JsonResponse
    {
        abort_unless($quiz->status === 'active', 404);
        $quiz->load('questions');

        return response()->json(['data' => $this->quizPayload($quiz)]);
    }

    public function complete(Request $request, Quiz $quiz): JsonResponse
    {
        abort_unless($quiz->status === 'active', 404);
        $quiz->load('questions');

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'in:a,b,c,d'],
        ]);

        $score = $quiz->questions->reduce(
            fn (int $total, $question): int => $total + ((($validated['answers'][$question->id] ?? null) === $question->correct_option) ? 1 : 0),
            0,
        );

        $identity = $request->user()->studentIdentity()->firstOrCreate([], [
            'pseudonymous_uuid' => $request->user()->pseudonymous_uuid,
        ]);

        $attempt = DB::transaction(fn () => $quiz->attempts()->create([
            'student_identity_id' => $identity->id,
            'score' => $score,
            'completed_at' => now(),
        ]));

        return response()->json([
            'data' => [
                'attempt_id' => $attempt->id,
                'score' => $score,
                'total_questions' => $quiz->questions->count(),
            ],
        ], 201);
    }

    private function quizPayload(Quiz $quiz): array
    {
        return [
            'id' => $quiz->id,
            'name' => $quiz->name,
            'category' => $quiz->category,
            'description' => $quiz->description,
            'status' => $quiz->status,
            'questions' => $quiz->questions->map(fn ($question): array => [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'options' => [
                    'a' => $question->option_a,
                    'b' => $question->option_b,
                    'c' => $question->option_c,
                    'd' => $question->option_d,
                ],
                'sort_order' => $question->sort_order,
                'explanation' => $question->explanation,
            ])->values()->all(),
        ];
    }
}
