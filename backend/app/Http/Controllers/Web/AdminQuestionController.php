<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StressQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminQuestionController extends Controller
{
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
        DB::transaction(fn () => $this->saveQuestion(new StressQuestion, $data));

        return redirect()->route('admin.questions.index')->with('status', 'Question created.');
    }

    public function edit(StressQuestion $question): View
    {
        return view('admin.questions.form', ['question' => $question->load('options')]);
    }

    public function update(Request $request, StressQuestion $question): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(fn () => $this->saveQuestion($question, $data));

        return redirect()->route('admin.questions.index')->with('status', 'Question updated.');
    }

    public function destroy(StressQuestion $question): RedirectResponse
    {
        if ($question->responses()->exists()) {
            $question->update(['is_active' => false]);

            return back()->with('status', 'Question has response history and was deactivated instead of deleted.');
        }

        DB::transaction(function () use ($question): void {
            $question->options()->delete();
            $question->delete();
        });

        return back()->with('status', 'Question deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'question_text' => ['required', 'string', 'max:2000'],
            'dimension' => ['nullable', 'string', 'max:100'],
            'question_type' => ['required', Rule::in(['scale'])],
            'position' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'is_sensitive' => ['nullable', 'boolean'],
            'options' => ['required', 'array', 'min:2', 'max:20'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.value' => ['required', 'string', 'max:100', 'distinct'],
            'options.*.score' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
        ]);
    }

    private function saveQuestion(StressQuestion $question, array $data): void
    {
        $question->fill([
            'question_text' => $data['question_text'],
            'dimension' => $data['dimension'] ?? null,
            'question_type' => $data['question_type'],
            'position' => $data['position'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
        ])->save();

        $retainedIds = [];
        foreach (array_values($data['options']) as $position => $option) {
            $optionId = $option['id'] ?? null;
            unset($option['id']);
            $values = $option + ['position' => $position + 1, 'is_active' => true];

            if ($optionId !== null) {
                $existing = $question->options()->whereKey($optionId)->firstOrFail();
                $existing->update($values);
                $retainedIds[] = $existing->id;
            } else {
                $retainedIds[] = $question->options()->create($values)->id;
            }
        }

        $question->options()->whereNotIn('id', $retainedIds)->update(['is_active' => false]);
    }
}
