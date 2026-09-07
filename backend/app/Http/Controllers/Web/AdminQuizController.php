<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminQuizController extends Controller
{
    public function index(): View
    {
        $quizzes = Quiz::query()
            ->withCount(['questions', 'attempts'])
            ->with(['attempts' => fn ($query) => $query->with('studentIdentity')->latest('completed_at')->limit(20)])
            ->orderBy('name')
            ->get();

        return view('admin.games-quizzes.index', [
            'quizzes' => $quizzes,
            'stats' => [
                'totalQuizzes' => Quiz::count(),
                'activeQuizzes' => Quiz::where('status', 'active')->count(),
                'totalQuestions' => DB::table('quiz_questions')->count(),
                'totalCompletions' => DB::table('quiz_attempts')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.games-quizzes.form', ['quiz' => new Quiz, 'questions' => collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $quiz = DB::transaction(function () use ($request): Quiz {
            $quiz = Quiz::create($this->validatedQuiz($request));
            $this->syncQuestions($quiz, $request);
            return $quiz;
        });

        return redirect()->route('admin.games-quizzes.edit', $quiz)->with('status', 'Quiz created.');
    }

    public function edit(Quiz $quiz): View
    {
        return view('admin.games-quizzes.form', [
            'quiz' => $quiz,
            'questions' => $quiz->questions()->get(),
        ]);
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        DB::transaction(function () use ($request, $quiz): void {
            $quiz->update($this->validatedQuiz($request));
            $this->syncQuestions($quiz, $request);
        });

        return redirect()->route('admin.games-quizzes.edit', $quiz)->with('status', 'Quiz updated.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $quiz->delete();
        return back()->with('status', 'Quiz deleted.');
    }

    private function validatedQuiz(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function syncQuestions(Quiz $quiz, Request $request): void
    {
        $questions = $request->validate([
            'questions' => ['nullable', 'array'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.question_text' => ['required', 'string', 'max:5000'],
            'questions.*.option_a' => ['required', 'string', 'max:1000'],
            'questions.*.option_b' => ['required', 'string', 'max:1000'],
            'questions.*.option_c' => ['required', 'string', 'max:1000'],
            'questions.*.option_d' => ['required', 'string', 'max:1000'],
            'questions.*.correct_option' => ['required', 'in:a,b,c,d'],
            'questions.*.explanation' => ['nullable', 'string', 'max:5000'],
        ])['questions'] ?? [];

        $keptIds = [];
        foreach ($questions as $position => $question) {
            $id = isset($question['id']) ? (int) $question['id'] : null;
            $record = $quiz->questions()->updateOrCreate(
                ['id' => $id ?: 0],
                [...collect($question)->except('id')->all(), 'sort_order' => $position + 1],
            );
            $keptIds[] = $record->id;
        }

        $quiz->questions()->when($keptIds, fn ($query) => $query->whereNotIn('id', $keptIds))->when(! $keptIds, fn ($query) => $query)->delete();
    }
}
