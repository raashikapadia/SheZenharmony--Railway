<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\StressQuestion;
use App\Models\StressScoreBand;
use App\Services\AssessmentAnalytics;
use App\Services\AssessmentScoringService;
use App\Services\QuestionnaireActivationService;
use App\Services\QuestionnaireAuditLogger;
use App\Services\QuestionnairePurger;
use App\Services\QuestionnaireReview;
use App\Services\QuestionnaireVersioner;
use App\Services\ScaleBandValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class AdminQuestionnaireController extends Controller
{
    public function __construct(private readonly QuestionnaireAuditLogger $audit) {}

    /** Overview: every questionnaire, drafts first, newest version first. */
    public function index(): View
    {
        $statusRank = ['draft' => 0, 'published' => 1, 'archived' => 2];
        $versions = Questionnaire::query()->notInTrash()
            ->withCount(['questions', 'sections', 'scoreBands'])
            ->get()
            ->sortBy([
                fn ($a, $b) => ($statusRank[$a->status] ?? 3) <=> ($statusRank[$b->status] ?? 3),
                fn ($a, $b) => $b->version <=> $a->version,
            ])
            ->values();

        return view('admin.questionnaires.index', [
            'versions' => $versions,
            'trashCount' => Questionnaire::query()->inTrash()->count(),
        ]);
    }

    /**
     * Live, dynamically computed figures for one questionnaire — never
     * hardcoded. Reuses the same publish-time validation for the health check.
     *
     * @return array<string, mixed>
     */
    private function workspaceStats(Questionnaire $questionnaire, QuestionnaireActivationService $activation): array
    {
        $questionnaire->loadMissing([
            'sections',
            'questions' => fn ($q) => $q->where('stress_questions.is_active', true)->with('options'),
            'scoreBands',
        ]);

        $maxRaw = (int) $questionnaire->questions->sum(
            fn ($question) => (int) $question->options->where('is_active', true)->max('score'),
        );

        $activeSections = $questionnaire->sections->where('is_active', true);
        $maxWeighted = $activeSections->isNotEmpty()
            ? round((float) $activeSections->sum('category_weight'), 2)
            : $maxRaw;

        $health = ['ok' => true, 'message' => null];
        try {
            $activation->preflight($questionnaire->fresh());
        } catch (ValidationException $e) {
            $health = ['ok' => false, 'message' => collect($e->errors())->flatten()->first()];
        }

        return [
            'max_raw' => $maxRaw,
            'max_weighted' => $maxWeighted,
            'weighted' => $activeSections->isNotEmpty(),
            'result_levels' => $questionnaire->scoreBands
                ->where('scope', StressScoreBand::SCOPE_OVERALL)->where('is_active', true)->count(),
            'stress_ranges' => $questionnaire->scoreBands
                ->where('scope', StressScoreBand::SCOPE_STRESS)->where('is_active', true)->count(),
            'health' => $health,
        ];
    }

    /** Step 1 of the guided "add a questionnaire" flow: just name it. */
    public function create(): View
    {
        return view('admin.questionnaires.create');
    }

    /**
     * Create the questionnaire as a draft and drop the admin straight into
     * the editor (Step 2) to add sections and questions. Type and version are
     * derived; publishing is a deliberate later step, never a side effect of
     * creation. Standard wellbeing result ranges are seeded so Step 3 opens
     * pre-filled rather than blank.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'published_at' => ['nullable', 'date'],
            'result_scale_min' => ['required', 'integer', 'min:-10000', 'max:10000'],
            'result_scale_max' => ['required', 'integer', 'min:-10000', 'max:10000', 'gt:result_scale_min'],
        ]);

        $questionnaire = DB::transaction(function () use ($request, $data): Questionnaire {
            $version = (int) Questionnaire::query()->where('type', 'stress')->max('version') + 1;

            // The client's result scale is entered, never assumed. Raw
            // totals are normalised onto it, so it survives any change to the
            // question count. The result categories on it are the admin's to
            // define in the editor — none are invented here.
            $questionnaire = Questionnaire::query()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => 'stress',
                'version' => $version,
                'result_scale_min' => $data['result_scale_min'],
                'result_scale_max' => $data['result_scale_max'],
                'status' => 'draft',
                'is_active' => false,
                'published_at' => $data['published_at'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);

            $this->audit->log($questionnaire->id, 'questionnaire.created', "Created draft questionnaire \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire, null, $data);

            return $questionnaire;
        });

        return redirect()
            ->route('admin.questionnaires.sections.index', $questionnaire)
            ->with('status', "Draft created. Add your sections and questions below — then publish, or keep it as a draft until you're ready.");
    }

    public function edit(Questionnaire $questionnaire): View
    {
        return $this->form($questionnaire->load(['questions', 'scoreBands']));
    }

    public function update(Request $request, Questionnaire $questionnaire, ScaleBandValidator $bandValidator, QuestionnaireActivationService $activationService): RedirectResponse
    {
        $data = $this->validated($request, $questionnaire);
        DB::transaction(function () use ($questionnaire, $data, $bandValidator, $activationService): void {
            $requested = $data['questionnaire'];
            $questionnaire->update(collect($requested)->except(['status', 'is_active'])->all());
            $this->syncConfiguration($questionnaire, $data, $bandValidator);
            if ($requested['status'] === 'published' || $requested['is_active']) {
                $activationService->activate($questionnaire);
            } else {
                $questionnaire->update(['status' => $requested['status'], 'is_active' => false]);
            }
            $this->audit->log($questionnaire->id, 'questionnaire.updated', "Updated questionnaire \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire, null, $requested);
        });

        return redirect()->route('admin.questionnaires.index')->with('status', 'Questionnaire updated.');
    }

    /**
     * Update only the questionnaire's labelling and publish state. Never
     * touches its questions or result ranges, so it is safe to call from the
     * Sections page where those are edited separately.
     */
    public function updateDetails(Request $request, Questionnaire $questionnaire, QuestionnaireActivationService $activationService): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'period' => ['nullable', 'string', 'max:100'],
            'version' => ['required', 'integer', 'min:1', Rule::unique('questionnaires')->where(fn ($query) => $query->where('type', $questionnaire->type))->ignore($questionnaire)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($questionnaire, $data, $request, $activationService): void {
            $questionnaire->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period' => $data['period'] ?? null,
                'version' => $data['version'],
                'published_at' => $data['published_at'] ?? $questionnaire->published_at,
            ]);

            if ($data['status'] === 'published' || $request->boolean('is_active')) {
                $activationService->activate($questionnaire);
            } else {
                $questionnaire->update(['status' => $data['status'], 'is_active' => false]);
            }

            $this->audit->log($questionnaire->id, 'questionnaire.updated', "Updated details of \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire, null, $data);
        });

        return redirect()
            ->route('admin.questionnaires.details', $questionnaire)
            ->with('status', 'Details saved.');
    }

    /**
     * Update only the result ranges (overall + stress bands).
     */
    public function updateRanges(Request $request, Questionnaire $questionnaire, ScaleBandValidator $bandValidator): RedirectResponse
    {
        $data = $request->validate([
            'bands' => ['nullable', 'array'],
            'bands.*.id' => ['nullable', 'integer'],
            'bands.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'bands.*.scope' => ['nullable', Rule::in([StressScoreBand::SCOPE_OVERALL, StressScoreBand::SCOPE_STRESS])],
            'bands.*.label' => ['required', 'string', 'max:100'],
            'bands.*.min_score' => ['required', 'integer'],
            'bands.*.max_score' => ['required', 'integer'],
            'bands.*.position' => ['required', 'integer', 'min:0'],
            'bands.*.is_active' => ['nullable', 'boolean'],
            'bands.*.intervention_id' => ['nullable', 'integer', 'exists:interventions,id'],
            'result_scale_min' => ['required_with:result_scale_max', 'nullable', 'integer', 'min:-10000', 'max:10000'],
            'result_scale_max' => ['required_with:result_scale_min', 'nullable', 'integer', 'min:-10000', 'max:10000', 'gt:result_scale_min'],
        ]);

        DB::transaction(function () use ($questionnaire, $data, $bandValidator): void {
            if (array_key_exists('result_scale_max', $data)) {
                $questionnaire->update([
                    'result_scale_min' => $data['result_scale_min'] ?? 0,
                    'result_scale_max' => $data['result_scale_max'],
                ]);
            }
            $this->assertBandsWithinScale($questionnaire, $data['bands'] ?? []);
            $this->syncBands($questionnaire, $data['bands'] ?? [], $bandValidator);
            $this->audit->log($questionnaire->id, 'questionnaire.updated', "Updated result ranges of \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire);
        });

        return redirect()
            ->route('admin.questionnaires.details', $questionnaire)
            ->with('status', 'Result scale and ranges saved.');
    }

    /** Questionnaire Management → Questionnaire Builder tab: open the live/draft version's editor. */
    public function builder(): RedirectResponse
    {
        $target = $this->primaryQuestionnaire();

        return $target
            ? redirect()->route('admin.questionnaires.sections.index', $target)
            : redirect()->route('admin.questionnaires.create');
    }

    /** Questionnaire Management → Results tab: completed assessments, wellbeing lens. */
    public function results(): View
    {
        return view('admin.questionnaires.results', [
            'assessments' => $this->completedAssessments(),
            'lens' => 'wellbeing',
        ]);
    }

    /** Questionnaire Management → Analytics tab (with in-page sub-tabs). */
    public function analytics(Request $request, AssessmentAnalytics $analytics, QuestionnaireActivationService $activation): View
    {
        $tab = $request->string('tab')->toString() ?: 'overall';
        $primary = $this->primaryQuestionnaire();
        $sections = $primary
            ? $primary->sections()->withCount('questions')->orderBy('position')->orderBy('id')->get()
            : collect();

        return view('admin.questionnaires.analytics', [
            'a' => $analytics->forTab($tab),
            'tab' => $tab,
            'lens' => 'wellbeing',
            'primary' => $primary,
            'structure' => $primary ? $this->workspaceStats($primary, $activation) : null,
            'structureSections' => $sections,
            'questionnaireCount' => Questionnaire::query()->notInTrash()->count(),
        ]);
    }

    /** Questionnaire Management → Versions tab. */
    public function versions(): View
    {
        $statusRank = ['draft' => 0, 'published' => 1, 'archived' => 2];
        $versions = Questionnaire::query()->notInTrash()
            ->withCount(['questions', 'sections', 'scoreBands'])
            ->get()
            ->sortBy([
                fn ($a, $b) => ($statusRank[$a->status] ?? 3) <=> ($statusRank[$b->status] ?? 3),
                fn ($a, $b) => $b->version <=> $a->version,
            ])->values();

        return view('admin.questionnaires.versions', [
            'versions' => $versions,
            'trashCount' => Questionnaire::query()->inTrash()->count(),
        ]);
    }

    private function primaryQuestionnaire(): ?Questionnaire
    {
        return Questionnaire::query()->notInTrash()->where('status', 'draft')->orderByDesc('version')->first()
            ?? Questionnaire::query()->notInTrash()->where('is_active', true)->orderByDesc('version')->first()
            ?? Questionnaire::query()->notInTrash()->orderByDesc('version')->first();
    }

    private function completedAssessments()
    {
        return StressAssessment::query()
            ->where('assessment_status', 'completed')
            ->with(['studentIdentity', 'questionnaire:id,title,version', 'wellbeingBand', 'stressBand', 'scoreBand'])
            ->orderByDesc('completed_at')
            ->paginate(25);
    }

    /**
     * Fork the given (usually published) questionnaire into a new draft
     * version and open its editor. The live version is never touched.
     */
    public function createVersion(Questionnaire $questionnaire, QuestionnaireVersioner $versioner): RedirectResponse
    {
        $draft = $versioner->draftFrom($questionnaire, auth()->id());
        $this->audit->log($draft->id, 'questionnaire.version_created', "Created draft v{$draft->version} from \"{$questionnaire->title}\" v{$questionnaire->version}.", $draft);

        return redirect()->route('admin.questionnaires.sections.index', $draft)->with(
            'status',
            "You're now editing draft v{$draft->version}. The live questionnaire (v{$questionnaire->version}) will not change until you publish this draft.",
        );
    }

    /**
     * Read-only "as a student would see it" view of a questionnaire. No
     * assessment attempt is created.
     */
    public function preview(Questionnaire $questionnaire): View
    {
        $questionnaire->load([
            'sections' => fn ($q) => $q->where('is_active', true)->orderBy('position')->orderBy('id'),
            'questions' => fn ($q) => $q->where('stress_questions.is_active', true)
                ->orderBy('questionnaire_questions.position')
                ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('position')]),
        ]);

        return view('admin.questionnaires.preview', [
            'questionnaire' => $questionnaire,
            'questionsBySection' => $questionnaire->questions->groupBy(fn ($q) => $q->pivot->questionnaire_section_id),
        ]);
    }

    /**
     * One-click publish. Makes this version live and turns every other
     * version back to a draft. If the questionnaire is not ready, redirects
     * back with the blocking reason instead of erroring.
     */
    /**
     * Step 1 — Details: name, description, go-live, the client's result
     * scale, and the result ranges with the support recommended for each.
     */
    public function details(Questionnaire $questionnaire, QuestionnaireReview $reviewer): View
    {
        $questionnaire->load(['scoreBands' => fn ($q) => $q->orderBy('scope')->orderBy('position')]);

        $rawSpan = $reviewer->rawSpan($questionnaire);
        $resultScale = $questionnaire->resultScale();
        $scoreSpan = $resultScale ?? $rawSpan;
        $overallBands = $questionnaire->scoreBands
            ->where('scope', StressScoreBand::SCOPE_OVERALL)
            ->where('is_active', true)
            ->sortBy('min_score')
            ->values();
        $questionCount = $questionnaire->questions()->count();

        return view('admin.questionnaires.details', [
            'questionnaire' => $questionnaire,
            'review' => $reviewer->run($questionnaire->fresh()),
            'questionCount' => $questionCount,
            'rawSpan' => $rawSpan,
            'resultScale' => $resultScale,
            'scoreSpan' => $scoreSpan,
            'overallBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_OVERALL)->values(),
            'rangeProblems' => $reviewer->rangeProblems($overallBands, $scoreSpan, $resultScale !== null),
            'interventions' => Intervention::query()->where('is_active', true)->orderBy('content_type')->orderBy('title')->get(['id', 'title', 'content_type']),
            'primaryInterventionByBand' => $reviewer->primaryInterventionByBand($questionnaire),
        ]);
    }

    /**
     * Review & Publish: the whole questionnaire checked in one place, with
     * every issue pointing at where to fix it, and the only Publish button.
     */
    public function review(Questionnaire $questionnaire, QuestionnaireReview $reviewer): View
    {
        return view('admin.questionnaires.review', [
            'questionnaire' => $questionnaire,
            'review' => $reviewer->run($questionnaire),
        ]);
    }

    public function publish(Questionnaire $questionnaire, QuestionnaireActivationService $activation, QuestionnaireReview $reviewer): RedirectResponse
    {
        // The review page is the gate; a stale form post gets sent back to it.
        $review = $reviewer->run($questionnaire);
        if (! $review['ready']) {
            $count = count($review['issues']);

            return redirect()->route('admin.questionnaires.review', $questionnaire)
                ->with('status', "Not published — {$count} ".Str::plural('item', $count).' still need attention.');
        }

        try {
            $activation->activate($questionnaire);
        } catch (ValidationException $e) {
            return redirect()->route('admin.questionnaires.review', $questionnaire)
                ->with('status', 'Not published — '.collect($e->errors())->flatten()->first());
        }

        $this->audit->log($questionnaire->id, 'questionnaire.published', "Published \"{$questionnaire->title}\" v{$questionnaire->version}; all other versions set to draft.", $questionnaire);

        return redirect()->route('admin.questionnaires.sections.index', $questionnaire)
            ->with('status', "\"{$questionnaire->title}\" is now published and available to students.");
    }

    public function archive(Questionnaire $questionnaire): RedirectResponse
    {
        $questionnaire->update(['status' => 'archived', 'is_active' => false]);
        $this->audit->log($questionnaire->id, 'questionnaire.archived', "Archived questionnaire \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire);

        return back()->with('status', 'Questionnaire archived.');
    }

    /**
     * Move a questionnaire to the trash. Questionnaires with assessment
     * history are never deletable — they are archived instead. Everything
     * else is kept, deactivated, and given a purge date a few days out; it
     * can be restored until then, after which the scheduled purge removes it.
     */
    public function destroy(Questionnaire $questionnaire): RedirectResponse
    {
        if ($questionnaire->assessments()->exists()) {
            $questionnaire->update(['status' => 'archived', 'is_active' => false]);
            $this->audit->log($questionnaire->id, 'questionnaire.archived', "Archived \"{$questionnaire->title}\" (v{$questionnaire->version}) — has assessment history, cannot be deleted.", $questionnaire);

            return back()->with('status', 'This questionnaire has assessment history, so it was archived instead of deleted.');
        }

        $purgeAfter = now()->addDays(Questionnaire::TRASH_RETENTION_DAYS);
        $questionnaire->update([
            'status' => 'archived',
            'is_active' => false,
            'trashed_at' => now(),
            'purge_after' => $purgeAfter,
        ]);
        $this->audit->log($questionnaire->id, 'questionnaire.trashed', "Moved \"{$questionnaire->title}\" (v{$questionnaire->version}) to trash; purges {$purgeAfter->toDateString()}.", $questionnaire);

        return redirect()->route('admin.questionnaires.index')->with(
            'status',
            "\"{$questionnaire->title}\" was moved to trash. It will be permanently deleted on {$purgeAfter->toFormattedDateString()} unless you restore it.",
        );
    }

    public function trash(): View
    {
        return view('admin.questionnaires.trash', [
            'questionnaires' => Questionnaire::query()->inTrash()
                ->withCount(['questions', 'sections', 'scoreBands'])
                ->orderByDesc('trashed_at')->paginate(20),
            'retentionDays' => Questionnaire::TRASH_RETENTION_DAYS,
        ]);
    }

    public function restore(int $questionnaire): RedirectResponse
    {
        $model = Questionnaire::query()->inTrash()->findOrFail($questionnaire);
        $model->update(['trashed_at' => null, 'purge_after' => null]);
        $this->audit->log($model->id, 'questionnaire.restored', "Restored \"{$model->title}\" (v{$model->version}) from trash.", $model);

        return redirect()->route('admin.questionnaires.trash')->with('status', "\"{$model->title}\" was restored. It is archived — publish it again when ready.");
    }

    /**
     * Permanently delete a trashed questionnaire now, skipping the wait.
     */
    public function forceDestroy(int $questionnaire, QuestionnairePurger $purger): RedirectResponse
    {
        $model = Questionnaire::query()->inTrash()->findOrFail($questionnaire);
        $title = $model->title;
        $version = $model->version;

        try {
            $purger->purge($model);
        } catch (RuntimeException) {
            $model->update(['trashed_at' => null, 'purge_after' => null]);

            return back()->with('status', 'This questionnaire has assessment history, so it cannot be deleted. It has been restored and archived.');
        }

        $this->audit->log(null, 'questionnaire.purged', "Permanently deleted \"{$title}\" (v{$version}).");

        return redirect()->route('admin.questionnaires.trash')->with('status', "\"{$title}\" was permanently deleted.");
    }

    public function scoring(Questionnaire $questionnaire, QuestionnaireActivationService $activation): View
    {
        $questionnaire->load([
            'sections' => fn ($query) => $query->withCount('questions'),
            'scoreBands' => fn ($query) => $query->orderBy('scope')->orderBy('position'),
        ]);

        $sumWeights = (float) $questionnaire->sections->where('is_active', true)->sum('category_weight');
        $activeQuestions = $questionnaire->questions()->where('stress_questions.is_active', true)->with('options')->get();
        $rawSpan = AssessmentScoringService::possibleTotalRange($activeQuestions);
        $scoreSpan = AssessmentScoringService::resultSpan($questionnaire, $activeQuestions);

        $validationError = null;
        try {
            $activation->preflight($questionnaire);
        } catch (ValidationException $e) {
            $validationError = collect($e->errors())->flatten()->first();
        }

        return view('admin.questionnaires.scoring', [
            'questionnaire' => $questionnaire,
            'sumWeights' => $sumWeights,
            'scoreSpan' => $scoreSpan,
            'rawSpan' => $rawSpan,
            'resultScale' => $questionnaire->resultScale(),
            'overallBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_OVERALL)->values(),
            'stressBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_STRESS)->values(),
            'validationError' => $validationError,
        ]);
    }

    private function form(Questionnaire $questionnaire): View
    {
        return view('admin.questionnaires.form', [
            'questionnaire' => $questionnaire,
            'availableQuestions' => StressQuestion::query()->orderBy('position')->orderBy('id')->get(),
            'sections' => $questionnaire->exists
                ? $questionnaire->sections()->orderBy('position')->get()
                : collect(),
        ]);
    }

    private function validated(Request $request, ?Questionnaire $questionnaire = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['stress'])],
            'version' => ['required', 'integer', 'min:1', Rule::unique('questionnaires')->where(fn ($query) => $query->where('type', $request->input('type')))->ignore($questionnaire)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'questions' => ['nullable', 'array'],
            'questions.*.id' => ['required', 'integer', 'distinct', 'exists:stress_questions,id'],
            'questions.*.position' => ['required', 'integer', 'min:0'],
            'questions.*.is_required' => ['nullable', 'boolean'],
            'questions.*.questionnaire_section_id' => [
                'nullable', 'integer',
                Rule::exists('questionnaire_sections', 'id')->where('questionnaire_id', $questionnaire?->id),
            ],
            'bands' => ['nullable', 'array'],
            'bands.*.id' => ['nullable', 'integer'],
            'bands.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'bands.*.scope' => ['nullable', Rule::in([StressScoreBand::SCOPE_OVERALL, StressScoreBand::SCOPE_STRESS])],
            'bands.*.label' => ['required', 'string', 'max:100'],
            'bands.*.min_score' => ['required', 'integer'],
            'bands.*.max_score' => ['required', 'integer'],
            'bands.*.position' => ['required', 'integer', 'min:0'],
            'bands.*.is_active' => ['nullable', 'boolean'],
        ]);

        $data['questionnaire'] = collect($data)->only(['title', 'description', 'type', 'version', 'status', 'published_at'])->all() + [
            'is_active' => $request->boolean('is_active'),
        ];

        return $data;
    }

    private function syncConfiguration(Questionnaire $questionnaire, array $data, ScaleBandValidator $validator): void
    {
        $questions = collect($data['questions'] ?? [])->mapWithKeys(fn ($item) => [
            $item['id'] => [
                'position' => $item['position'],
                'is_required' => (bool) ($item['is_required'] ?? false),
                'questionnaire_section_id' => $item['questionnaire_section_id'] ?? null,
            ],
        ])->all();
        $questionnaire->questions()->sync($questions);

        $this->syncBands($questionnaire, $data['bands'] ?? [], $validator);
    }

    /**
     * Upsert the given band rows, validating no-overlap per scope, and
     * deactivate any existing band left out of the submission.
     *
     * @param  array<int, array<string, mixed>>  $submitted
     */
    private function syncBands(Questionnaire $questionnaire, array $submitted, ScaleBandValidator $validator): void
    {
        $bands = collect($submitted)->map(function (array $item) {
            $item['scope'] = $item['scope'] ?? StressScoreBand::SCOPE_OVERALL;
            $item['is_active'] = (bool) ($item['is_active'] ?? false);

            return $item;
        });

        // Overlap is only meaningful within one scope's range.
        $bands->groupBy('scope')->each(fn ($group) => $validator->validateCollection($group->values()->all()));

        $retained = [];
        foreach ($bands as $item) {
            $band = isset($item['id']) ? $questionnaire->scoreBands()->whereKey($item['id'])->firstOrFail() : null;
            $interventionId = array_key_exists('intervention_id', $item) ? $item['intervention_id'] : false;
            unset($item['id'], $item['intervention_id']);
            $band ??= $questionnaire->scoreBands()->make();
            $band->fill($item + ['created_by_user_id' => $questionnaire->created_by_user_id])->save();
            $retained[] = $band->id;

            if ($interventionId !== false) {
                $this->setPrimaryRecommendation($band, $interventionId === null ? null : (int) $interventionId);
            }
        }
        $questionnaire->scoreBands()->whereNotIn('id', $retained)->update(['is_active' => false]);
    }

    /**
     * A result range has to sit on the client's scale: nothing below its
     * minimum, nothing above its maximum, and no range that runs backwards.
     *
     * @param  array<int, array<string, mixed>>  $submitted
     */
    private function assertBandsWithinScale(Questionnaire $questionnaire, array $submitted): void
    {
        $scale = $questionnaire->resultScale();
        if ($scale === null) {
            return;
        }
        [$min, $max] = $scale;

        foreach (array_values($submitted) as $index => $band) {
            if (($band['scope'] ?? StressScoreBand::SCOPE_OVERALL) !== StressScoreBand::SCOPE_OVERALL || ! ($band['is_active'] ?? true)) {
                continue;
            }
            $from = (int) $band['min_score'];
            $to = (int) $band['max_score'];
            $label = trim((string) ($band['label'] ?? '')) ?: 'Range '.($index + 1);
            if ($from > $to) {
                throw ValidationException::withMessages(["bands.{$index}.min_score" => "\"{$label}\" runs from {$from} to {$to} — the minimum can't be higher than the maximum."]);
            }
            if ($from < $min || $to > $max) {
                throw ValidationException::withMessages(["bands.{$index}.min_score" => "\"{$label}\" ({$from}–{$to}) falls outside the result scale {$min}–{$max}."]);
            }
        }
    }

    /**
     * Make `$interventionId` the support item recommended first for this
     * range. Other items linked to the range keep their link but sort after
     * it; passing null drops the current first item.
     */
    private function setPrimaryRecommendation(StressScoreBand $band, ?int $interventionId): void
    {
        $current = $band->recommendations()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        if ($interventionId === null) {
            $current?->update(['is_active' => false]);

            return;
        }

        $band->recommendations()->where('is_active', true)->where('priority', 0)->update(['priority' => 1]);
        $band->recommendations()->updateOrCreate(
            ['intervention_id' => $interventionId],
            ['is_active' => true, 'priority' => 0],
        );
    }
}
