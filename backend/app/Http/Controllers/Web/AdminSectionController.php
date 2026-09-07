<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CategoryResult;
use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressQuestion;
use App\Models\StressScoreBand;
use App\Services\QuestionnaireActivationService;
use App\Services\QuestionnaireAuditLogger;
use App\Services\QuestionWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function index(Questionnaire $questionnaire, QuestionnaireActivationService $activation): View
    {
        $questionnaire->load([
            'sections' => fn ($query) => $query->withCount('questions'),
            'scoreBands' => fn ($query) => $query->orderBy('scope')->orderBy('position'),
        ]);

        // Questions grouped by their section membership in THIS questionnaire.
        $questionsBySection = $questionnaire->questions()
            ->with('options')
            ->orderBy('questionnaire_questions.position')
            ->get()
            ->groupBy(fn ($question) => $question->pivot->questionnaire_section_id);

        $sumWeights = (float) $questionnaire->sections->where('is_active', true)->sum('category_weight');

        $publishError = null;
        try {
            // A clean instance so preflight loads its own scoped relations.
            $activation->preflight($questionnaire->fresh());
        } catch (ValidationException $e) {
            $publishError = collect($e->errors())->flatten()->first();
        }

        $activeSections = $questionnaire->sections->where('is_active', true);
        $questionCount = $questionsBySection->reduce(fn ($carry, $group) => $carry + $group->count(), 0);

        return view('admin.sections.index', [
            'questionnaire' => $questionnaire,
            'questionsBySection' => $questionsBySection,
            'overallBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_OVERALL)->values(),
            'stressBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_STRESS)->values(),
            'sumWeights' => $sumWeights,
            'publishError' => $publishError,
            'sectionCount' => $activeSections->count(),
            'questionCount' => $questionCount,
            // The guided 3-step strip is for a draft still being built, not for
            // the live version or an archived one.
            'showWizard' => $questionnaire->status === 'draft' && ! $questionnaire->is_active,
        ]);
    }

    public function create(Questionnaire $questionnaire): View
    {
        return view('admin.sections.form', [
            'questionnaire' => $questionnaire,
            'section' => new QuestionnaireSection(['category_weight' => 1, 'is_active' => true]),
        ]);
    }

    public function store(Request $request, Questionnaire $questionnaire): RedirectResponse
    {
        $data = $this->validated($request);
        $section = $questionnaire->sections()->create($data + [
            'position' => $data['position'] ?? ((int) $questionnaire->sections()->max('position') + 1),
        ]);

        $this->audit->log($questionnaire->id, 'section.created', "Added section \"{$section->title}\".", $section, null, $data);

        return redirect()
            ->route('admin.questionnaires.sections.index', $questionnaire)
            ->with('status', 'Section added.');
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

        $referencedByHistory = $section->questions()->exists()
            || CategoryResult::query()->where('questionnaire_section_id', $section->id)->exists();

        if ($referencedByHistory) {
            $section->update(['is_active' => false]);
            $this->audit->log($questionnaire->id, 'section.archived', "Archived section \"{$section->title}\".", $section);

            return back()->with('status', 'Section is in use, so it was archived instead of deleted.');
        }

        $this->audit->log($questionnaire->id, 'section.deleted', "Deleted section \"{$section->title}\".", $section);
        $section->delete();

        return back()->with('status', 'Section deleted.');
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
            $questionnaire->questions()->detach($question->id);

            if ($question->responses()->exists()) {
                $question->update(['is_active' => false]);
                $this->audit->log($questionnaire->id, 'question.archived', "Removed and archived \"{$question->question_text}\" (has response history).", $question);

                return back()->with('status', 'Question has response history — removed from this section and archived.');
            }

            if ($question->questionnaires()->exists()) {
                $this->audit->log($questionnaire->id, 'question.detached', "Removed \"{$question->question_text}\" from section \"{$section->title}\".", $question);

                return back()->with('status', 'Question removed from this section (still used elsewhere).');
            }

            $this->audit->log($questionnaire->id, 'question.deleted', "Deleted \"{$question->question_text}\".", $question);
            $question->options()->delete();
            $question->delete();

            return back()->with('status', 'Question deleted.');
        });
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'category_weight' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return array_merge($validated, ['is_active' => $request->boolean('is_active')]);
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
}
