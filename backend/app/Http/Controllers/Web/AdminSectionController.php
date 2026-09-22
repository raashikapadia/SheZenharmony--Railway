<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CategoryResult;
use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Services\AssessmentScoringService;
use App\Services\QuestionnaireAuditLogger;
use App\Services\QuestionnaireReview;
use App\Services\QuestionnaireVersioner;
use App\Services\QuestionWriter;
use App\Support\AnswerScalePresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Blade admin CRUD for a questionnaire's wellbeing categories (sections) and
 * the questions inside each one. Sections are never hard-deleted once a
 * completed assessment references them through category_results — they are
 * archived instead, preserving historical breakdowns. Questions with
 * response history are likewise archived, not deleted.
 */
class AdminSectionController extends Controller
{
    public function __construct(
        private readonly QuestionnaireAuditLogger $audit,
        private readonly QuestionWriter $writer,
    ) {}

    public function index(Questionnaire $questionnaire, QuestionnaireReview $reviewer): View
    {
        $questionnaire->load(['sections' => fn ($query) => $query->withCount('questions')]);

        // Questions grouped by their section membership in THIS questionnaire.
        $questionsBySection = $questionnaire->questions()
            ->with('options')
            ->orderBy('questionnaire_questions.position')
            ->get()
            ->groupBy(fn ($question) => $question->pivot->questionnaire_section_id);

        $activeSections = $questionnaire->sections->where('is_active', true);
        $questionCount = $questionsBySection->reduce(fn ($carry, $group) => $carry + $group->count(), 0);

        // The weight each section actually scores with (derived when the
        // questionnaire shares weights equally), keyed by section id.
        $effectiveWeights = AssessmentScoringService::effectiveSectionWeights($questionnaire, $activeSections->values());

        return view('admin.sections.index', [
            'questionnaire' => $questionnaire,
            'questionsBySection' => $questionsBySection,
            // The same whole-questionnaire review the Review & Publish page
            // shows, so the step strip never disagrees with it.
            'review' => $reviewer->run($questionnaire),
            'sectionCount' => $activeSections->count(),
            'questionCount' => $questionCount,
            'effectiveWeights' => $effectiveWeights,
            'weightTotal' => array_sum($effectiveWeights),
        ]);
    }

    public function create(Questionnaire $questionnaire): View
    {
        return view('admin.sections.form', [
            'questionnaire' => $questionnaire,
            'section' => new QuestionnaireSection(['category_weight' => 1, 'is_active' => true]),
        ]);
    }

    /**
     * Copy a section and every question in it (fresh question and option
     * rows, so editing the copy never touches the original), appended to the
     * end of the questionnaire.
     */
    public function duplicate(Questionnaire $questionnaire, QuestionnaireSection $section, QuestionnaireVersioner $versioner): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);

        $copy = DB::transaction(function () use ($questionnaire, $section, $versioner): QuestionnaireSection {
            $copy = $questionnaire->sections()->create([
                'title' => $section->title.' (copy)',
                'description' => $section->description,
                'position' => (int) $questionnaire->sections()->max('position') + 1,
                'category_weight' => $section->category_weight,
                'is_active' => $section->is_active,
            ]);

            $questions = $questionnaire->questions()
                ->wherePivot('questionnaire_section_id', $section->id)
                ->with('options')
                ->orderBy('questionnaire_questions.position')
                ->get();
            $position = (int) $questionnaire->questions()->max('questionnaire_questions.position');
            foreach ($questions as $question) {
                $clone = $versioner->cloneQuestion($question, auth()->id());
                $clone->update(['dimension' => $copy->title]);
                $questionnaire->questions()->attach($clone->id, [
                    'questionnaire_section_id' => $copy->id,
                    'position' => ++$position,
                    'is_required' => (bool) $question->pivot->is_required,
                ]);
            }

            return $copy;
        });

        $this->audit->log($questionnaire->id, 'section.duplicated', "Duplicated section \"{$section->title}\" as \"{$copy->title}\".", $copy);

        return redirect()
            ->route('admin.questionnaires.sections.index', $questionnaire)
            ->withFragment('section-'.$copy->id)
            ->with('status', "Section \"{$section->title}\" duplicated. Rename the copy and adjust its questions as needed.");
    }

    public function store(Request $request, Questionnaire $questionnaire): RedirectResponse
    {
        $data = $this->validated($request);
        $bulk = $this->bulkValidated($request);

        $section = DB::transaction(function () use ($questionnaire, $data, $bulk): QuestionnaireSection {
            $section = $questionnaire->sections()->create($data + [
                'position' => $data['position'] ?? ((int) $questionnaire->sections()->max('position') + 1),
            ]);
            // The section's first questions can come along in the same save —
            // one per line — so a category is usable the moment it exists.
            $this->attachQuestions($questionnaire, $section, $bulk['lines'], $bulk['scale']);

            return $section;
        });

        $this->audit->log($questionnaire->id, 'section.created', "Added section \"{$section->title}\".", $section, null, $data);

        $added = count($bulk['lines']);

        return redirect()
            ->route('admin.questionnaires.sections.index', $questionnaire)
            ->with('status', $added
                ? "Section added with {$added} question".($added === 1 ? '' : 's').'.'
                : 'Section added.');
    }

    /**
     * Add several questions to a section at once: one question per line, all
     * sharing a chosen answer scale. This is how a long questionnaire gets
     * built in minutes rather than a form per question.
     */
    public function storeQuestions(Request $request, Questionnaire $questionnaire, QuestionnaireSection $section): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);
        $bulk = $this->bulkValidated($request, required: true);

        DB::transaction(fn () => $this->attachQuestions($questionnaire, $section, $bulk['lines'], $bulk['scale']));

        $added = count($bulk['lines']);
        $this->audit->log($questionnaire->id, 'question.created', "Added {$added} question".($added === 1 ? '' : 's')." to section \"{$section->title}\".", $section, null, $bulk);

        return redirect()
            ->route('admin.questionnaires.sections.index', $questionnaire)
            ->with('status', "{$added} question".($added === 1 ? '' : 's').' added to '.$section->title.'.');
    }

    /** Swap a section with its neighbour in the given direction. */
    public function moveSection(Request $request, Questionnaire $questionnaire, QuestionnaireSection $section): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ordered = $questionnaire->sections()->orderBy('position')->orderBy('id')->get();
        $this->swapNeighbours($ordered, $section->id, $direction, function (QuestionnaireSection $a, QuestionnaireSection $b): void {
            [$a->position, $b->position] = [$b->position, $a->position];
            // Ties (two sections sharing a position) would otherwise not move.
            if ($a->position === $b->position) {
                $b->position = $a->position + 1;
            }
            $a->save();
            $b->save();
        });

        return back()->with('status', 'Section order updated.');
    }

    /** Swap a question with its neighbour within the same section. */
    public function moveQuestion(Request $request, Questionnaire $questionnaire, QuestionnaireSection $section, StressQuestion $question): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);
        $this->ensureQuestionInSection($questionnaire, $section, $question);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $ordered = $questionnaire->questions()
            ->wherePivot('questionnaire_section_id', $section->id)
            ->orderBy('questionnaire_questions.position')
            ->orderBy('stress_questions.id')
            ->get();
        $this->swapNeighbours($ordered, $question->id, $direction, function (StressQuestion $a, StressQuestion $b) use ($questionnaire): void {
            $positionA = (int) $a->pivot->position;
            $positionB = (int) $b->pivot->position;
            if ($positionA === $positionB) {
                $positionB++;
            }
            $questionnaire->questions()->updateExistingPivot($a->id, ['position' => $positionB]);
            $questionnaire->questions()->updateExistingPivot($b->id, ['position' => $positionA]);
        });

        return back()->with('status', 'Question order updated.');
    }

    public function edit(Questionnaire $questionnaire, QuestionnaireSection $section): View
    {
        $this->ensureOwnership($questionnaire, $section);

        return view('admin.sections.form', compact('questionnaire', 'section'));
    }

    public function update(Request $request, Questionnaire $questionnaire, QuestionnaireSection $section): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);
        $old = $section->only(['title', 'description', 'position', 'category_weight', 'is_active']);
        $data = $this->validated($request);
        $section->update($data);

        $this->audit->log($questionnaire->id, 'section.updated', "Updated section \"{$section->title}\".", $section, $old, $data);

        return redirect()
            ->route('admin.questionnaires.sections.index', $questionnaire)
            ->with('status', 'Section updated.');
    }

    public function destroy(Questionnaire $questionnaire, QuestionnaireSection $section): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);

        return DB::transaction(function () use ($questionnaire, $section): RedirectResponse {
            // A section goes with its questions: they leave the questionnaire
            // too, so nothing is left over that students would still have to
            // answer. Questions with response history are archived rather
            // than deleted, as when removing them one by one.
            $questions = $questionnaire->questions()
                ->wherePivot('questionnaire_section_id', $section->id)
                ->get();
            foreach ($questions as $question) {
                $this->removeQuestion($questionnaire, $question);
            }
            $removed = $questions->count();

            $referencedByHistory = CategoryResult::query()->where('questionnaire_section_id', $section->id)->exists();
            $suffix = $removed ? " and its {$removed} question".($removed === 1 ? '' : 's') : '';

            if ($referencedByHistory) {
                $section->update(['is_active' => false]);
                $this->audit->log($questionnaire->id, 'section.archived', "Archived section \"{$section->title}\"{$suffix}.", $section);

                return back()->with('status', "Section \"{$section->title}\"{$suffix} removed. Past results that mention it are kept.");
            }

            $this->audit->log($questionnaire->id, 'section.deleted', "Deleted section \"{$section->title}\"{$suffix}.", $section);
            $section->delete();

            return back()->with('status', "Section \"{$section->title}\"{$suffix} deleted.");
        });
    }

    /**
     * Take a question out of this questionnaire. It is deleted outright when
     * nothing else refers to it, archived when students have answered it,
     * and merely detached when another questionnaire still uses it.
     *
     * @return 'deleted'|'archived'|'detached'
     */
    private function removeQuestion(Questionnaire $questionnaire, StressQuestion $question): string
    {
        $questionnaire->questions()->detach($question->id);

        if ($question->responses()->exists()) {
            $question->update(['is_active' => false]);

            return 'archived';
        }
        if ($question->questionnaires()->exists()) {
            return 'detached';
        }

        $question->options()->delete();
        $question->delete();

        return 'deleted';
    }

    // ---- Questions within a section ------------------------------------------

    public function createQuestion(Questionnaire $questionnaire, QuestionnaireSection $section): View
    {
        $this->ensureOwnership($questionnaire, $section);

        return view('admin.questions.form', [
            'question' => new StressQuestion(['is_active' => true, 'wellbeing_weight' => 1, 'stress_weight' => 1]),
            'questionnaire' => $questionnaire,
            'section' => $section,
            'isRequired' => true,
        ]);
    }

    public function storeQuestion(Request $request, Questionnaire $questionnaire, QuestionnaireSection $section): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);
        $data = $this->questionValidated($request);

        $question = new StressQuestion(['dimension' => $section->title]);
        DB::transaction(function () use ($question, $data, $questionnaire, $section): void {
            $this->writer->save($question, $data);
            $questionnaire->questions()->attach($question->id, [
                'questionnaire_section_id' => $section->id,
                'position' => (int) $questionnaire->questions()->max('questionnaire_questions.position') + 1,
                'is_required' => (bool) ($data['is_required'] ?? true),
            ]);
        });

        $this->audit->log($questionnaire->id, 'question.created', "Added question to section \"{$section->title}\": \"{$question->question_text}\".", $question, null, $data);

        return redirect()
            ->route('admin.questionnaires.sections.index', $questionnaire)
            ->with('status', 'Question added to '.$section->title.'.');
    }

    public function editQuestion(Questionnaire $questionnaire, QuestionnaireSection $section, StressQuestion $question): View
    {
        $this->ensureOwnership($questionnaire, $section);
        $this->ensureQuestionInSection($questionnaire, $section, $question);

        $membership = $questionnaire->questions()->where('stress_questions.id', $question->id)->first();

        return view('admin.questions.form', [
            'question' => $question->load('options'),
            'questionnaire' => $questionnaire,
            'section' => $section,
            'isRequired' => (bool) ($membership?->pivot->is_required ?? true),
        ]);
    }

    public function updateQuestion(Request $request, Questionnaire $questionnaire, QuestionnaireSection $section, StressQuestion $question): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);
        $this->ensureQuestionInSection($questionnaire, $section, $question);
        $data = $this->questionValidated($request);

        DB::transaction(function () use ($question, $data, $questionnaire, $section): void {
            $this->writer->save($question, $data);
            $questionnaire->questions()->updateExistingPivot($question->id, [
                'is_required' => (bool) ($data['is_required'] ?? true),
                'questionnaire_section_id' => $section->id,
            ]);
        });

        $this->audit->log($questionnaire->id, 'question.updated', "Updated question in section \"{$section->title}\": \"{$question->question_text}\".", $question, null, $data);

        return redirect()
            ->route('admin.questionnaires.sections.index', $questionnaire)
            ->with('status', 'Question updated.');
    }

    public function destroyQuestion(Questionnaire $questionnaire, QuestionnaireSection $section, StressQuestion $question): RedirectResponse
    {
        $this->ensureOwnership($questionnaire, $section);
        $this->ensureQuestionInSection($questionnaire, $section, $question);

        return DB::transaction(function () use ($questionnaire, $section, $question): RedirectResponse {
            $outcome = $this->removeQuestion($questionnaire, $question);

            $this->audit->log($questionnaire->id, "question.{$outcome}", match ($outcome) {
                'archived' => "Removed and archived \"{$question->question_text}\" (has response history).",
                'detached' => "Removed \"{$question->question_text}\" from section \"{$section->title}\".",
                default => "Deleted \"{$question->question_text}\".",
            }, $question);

            return back()->with('status', match ($outcome) {
                'archived' => 'Question removed. Students have already answered it, so their past results are kept.',
                'detached' => 'Question removed from this section.',
                default => 'Question deleted.',
            });
        });
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'category_weight' => ['nullable', 'numeric', 'min:0.01', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
            // The weight only matters under custom weighting; a section made
            // without one counts as 1 until the admin sets weights.
            'category_weight' => $validated['category_weight'] ?? 1,
        ]);
    }

    private function ensureOwnership(Questionnaire $questionnaire, QuestionnaireSection $section): void
    {
        abort_unless($section->questionnaire_id === $questionnaire->id, 404);
    }

    private function ensureQuestionInSection(Questionnaire $questionnaire, QuestionnaireSection $section, StressQuestion $question): void
    {
        abort_unless(
            $questionnaire->questions()
                ->where('stress_questions.id', $question->id)
                ->wherePivot('questionnaire_section_id', $section->id)
                ->exists(),
            404,
            'Question not found in this section.',
        );
    }

    /** @return array<string, mixed> */
    private function questionValidated(Request $request): array
    {
        return $request->validate(
            QuestionWriter::rules() + ['is_required' => ['nullable', 'boolean']],
        );
    }

    /**
     * The "one question per line" box and its answer scale. Blank lines are
     * ignored; with `$required` the box must hold at least one question.
     *
     * @return array{lines: array<int, string>, scale: string}
     */
    private function bulkValidated(Request $request, bool $required = false): array
    {
        $validated = $request->validate([
            'questions_text' => [$required ? 'required' : 'nullable', 'string', 'max:200000'],
            'scale' => ['nullable', Rule::in(AnswerScalePresets::keys())],
        ]);

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $validated['questions_text'] ?? '') ?: []),
            fn (string $line) => $line !== '',
        ));

        if ($required && $lines === []) {
            throw ValidationException::withMessages([
                'questions_text' => 'Type at least one question — one per line.',
            ]);
        }

        foreach ($lines as $index => $line) {
            if (mb_strlen($line) > 2000) {
                throw ValidationException::withMessages([
                    'questions_text' => 'Question '.($index + 1).' is longer than 2000 characters.',
                ]);
            }
        }

        return ['lines' => $lines, 'scale' => $validated['scale'] ?? AnswerScalePresets::DEFAULT];
    }

    /**
     * Create one required, active question per line with the preset's
     * options, appended to the end of the section in the order typed.
     *
     * @param  array<int, string>  $lines
     */
    private function attachQuestions(Questionnaire $questionnaire, QuestionnaireSection $section, array $lines, string $scale): void
    {
        if ($lines === []) {
            return;
        }

        $preset = AnswerScalePresets::get($scale);
        $position = (int) $questionnaire->questions()->max('questionnaire_questions.position');

        foreach ($lines as $text) {
            $question = new StressQuestion(['dimension' => $section->title]);
            $this->writer->save($question, [
                'question_text' => $text,
                'question_type' => $preset['type'],
                'is_active' => true,
                'options' => $preset['options'],
            ]);
            $questionnaire->questions()->attach($question->id, [
                'questionnaire_section_id' => $section->id,
                'position' => ++$position,
                'is_required' => true,
            ]);
        }
    }

    /**
     * Find `$id` in `$ordered` and hand it and its up/down neighbour to
     * `$swap`. Nothing happens at either end of the list.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, T>  $ordered
     * @param  callable(T, T): void  $swap
     */
    private function swapNeighbours($ordered, int $id, string $direction, callable $swap): void
    {
        $index = $ordered->search(fn ($item) => $item->id === $id);
        if ($index === false) {
            return;
        }
        $neighbour = $direction === 'up' ? $index - 1 : $index + 1;
        if ($neighbour < 0 || $neighbour >= $ordered->count()) {
            return;
        }

        DB::transaction(fn () => $swap($ordered[$index], $ordered[$neighbour]));
    }
}
