<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StressQuestion;
use App\Services\QuestionnaireAuditLogger;
use App\Services\QuestionWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminQuestionController extends Controller
{
    public function __construct(
        private readonly QuestionnaireAuditLogger $audit,
        private readonly QuestionWriter $writer,
    ) {}

    public function index(): View
    {
        return view('admin.questions.index', [
            'questions' => StressQuestion::query()->with('options')->orderBy('position')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.questions.form', ['question' => new StressQuestion]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $question = new StressQuestion;
        DB::transaction(fn () => $this->writer->save($question, $data));
        $this->audit->log(null, 'question.created', "Created question \"{$question->question_text}\".", $question, null, $data);

        return redirect()->route('admin.questions.index')->with('status', 'Question created.');
    }

    public function edit(StressQuestion $question): View
    {
        return view('admin.questions.form', ['question' => $question->load('options')]);
    }

    public function update(Request $request, StressQuestion $question): RedirectResponse
    {
        $data = $this->validated($request);
        $old = $question->only([
            'question_text', 'question_type', 'wellbeing_weight', 'is_reverse_scored',
            'stress_relevant', 'stress_direction', 'stress_weight', 'min_score', 'max_score',
        ]);
        DB::transaction(fn () => $this->writer->save($question, $data));
        $this->audit->log(null, 'question.updated', "Updated question \"{$question->question_text}\".", $question, $old, $data);

        return redirect()->route('admin.questions.index')->with('status', 'Question updated.');
    }

    public function destroy(StressQuestion $question): RedirectResponse
    {
        if ($question->responses()->exists()) {
            $question->update(['is_active' => false]);
            $this->audit->log(null, 'question.archived', "Archived question \"{$question->question_text}\" (has response history).", $question);

            return back()->with('status', 'Question has response history and was deactivated instead of deleted.');
        }

        $this->audit->log(null, 'question.deleted', "Deleted question \"{$question->question_text}\".", $question);
        DB::transaction(function () use ($question): void {
            $question->options()->delete();
            $question->delete();
        });

        return back()->with('status', 'Question deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        // The standalone bank form always carries an explicit position.
        return $request->validate(
            array_merge(QuestionWriter::rules(), ['position' => ['required', 'integer', 'min:0', 'max:10000']]),
        );
    }
}
