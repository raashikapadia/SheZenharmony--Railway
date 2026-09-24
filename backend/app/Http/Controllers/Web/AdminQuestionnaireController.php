<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\InterventionRecommendation;
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
use App\Services\RecommendedInterventionService;
use App\Services\ScaleBandValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * The Blade questionnaire workspace. Building a questionnaire is five
 * screens, always in the same order and always reachable:
 *
 *   1 Basic info → 2 Sections & questions → 3 Scoring → 4 Result levels → 5 Review & publish
 *
 * Every figure an admin sees (maximum score, weights, spans, coverage) is
 * computed live by the scoring engine from the stored configuration.
 */
class AdminQuestionnaireController extends Controller
{
    public function __construct(private readonly QuestionnaireAuditLogger $audit) {}

    /** Overview: every questionnaire, drafts first, newest version first. */
    public function index(): View
    {
        session()->forget('admin_questionnaire_creation_id');
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
        $overview = app(AssessmentScoringService::class)->overview($questionnaire);
        $questionnaire->loadMissing('scoreBands');

        $health = ['ok' => true, 'message' => null];
        try {
            $activation->preflight($questionnaire->fresh());
        } catch (ValidationException $e) {
            $health = ['ok' => false, 'message' => collect($e->errors())->flatten()->first()];
        }

        return [
            'max_raw' => $overview['total_span'][1],
            'max_weighted' => $overview['weight_total'],
            'weighted' => $overview['section_count'] > 0,
            'method' => $questionnaire->scoringMethodLabel(),
            'result_levels' => $questionnaire->scoreBands
                ->where('scope', StressScoreBand::SCOPE_OVERALL)->where('is_active', true)->count(),
            'stress_ranges' => $questionnaire->scoreBands
                ->where('scope', StressScoreBand::SCOPE_STRESS)->where('is_active', true)->count(),
            'health' => $health,
            'overview' => $overview,
        ];
    }

    /** Open the focused form that starts a new draft questionnaire. */
    public function create(): View
    {
        return view('admin.questionnaires.create');
    }

    /**
     * Create an inactive draft and open its Basic info step. Type and version
     * are derived; publishing remains a deliberate action on Review & Publish.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'published_at' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            // Results are reported either as a percentage (a 0–100 scale the
            // engine fills in) or on a scale the admin types in.
            'result_basis' => ['nullable', Rule::in(['percentage', 'scale'])],
            'result_scale_min' => ['required_unless:result_basis,percentage', 'nullable', 'integer', 'min:-10000', 'max:10000'],
            'result_scale_max' => ['required_unless:result_basis,percentage', 'nullable', 'integer', 'min:-10000', 'max:10000', 'gt:result_scale_min'],
            'scoring_method' => ['nullable', Rule::in(Questionnaire::scoringMethods())],
            'section_weighting' => ['nullable', Rule::in(Questionnaire::sectionWeightings())],
        ]);

        $questionnaire = DB::transaction(function () use ($request, $data): Questionnaire {
            [$type, $version] = $this->familyFor($data['title']);

            $percentage = ($data['result_basis'] ?? null) === 'percentage';

            // New questionnaires start on the simplest configuration: every
            // section counts the same, each contributes its share of the
            // result, and the result reads as a percentage. All of it can be
            // changed on the Scoring step.
            $questionnaire = Questionnaire::query()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $type,
                'version' => $version,
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'result_scale_min' => $percentage ? 0 : $data['result_scale_min'],
                'result_scale_max' => $percentage ? 100 : $data['result_scale_max'],
                'scoring_method' => $data['scoring_method'] ?? Questionnaire::SCORING_WEIGHTED_SECTIONS,
                'section_weighting' => $data['section_weighting'] ?? Questionnaire::WEIGHTING_EQUAL,
                'status' => 'draft',
                'is_active' => false,
                'published_at' => $data['published_at'] ?? null,
                'created_by_user_id' => $request->user()->id,
            ]);

            $this->audit->log($questionnaire->id, 'questionnaire.created', "Created draft questionnaire \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire, null, $data);

            return $questionnaire;
        });

        $request->session()->put('admin_questionnaire_creation_id', $questionnaire->id);

        return redirect()
            ->route('admin.questionnaires.show-details', $questionnaire)
            ->with('status', 'Questionnaire created successfully! Review its details, then continue to sections and questions.');
    }

    /**
     * The version family and next version number a questionnaire belongs to.
     * Each questionnaire gets its own family, derived from its title, so it
     * versions independently of every other one.
     *
     * @return array{0: string, 1: int}
     */
    private function familyFor(string $title, ?int $ignoreId = null): array
    {
        $type = Questionnaire::deriveType($title, $ignoreId);
        $version = (int) Questionnaire::query()->where('type', $type)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->max('version') + 1;

        return [$type, $version];
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
     * Step 1 save: the questionnaire's labelling and publish state. Never
     * touches its questions, scoring or result levels.
     */
    public function updateDetails(Request $request, Questionnaire $questionnaire, QuestionnaireActivationService $activationService): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'period' => ['nullable', 'string', 'max:100'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'version' => ['required', 'integer', 'min:1', Rule::unique('questionnaires')->where(fn ($query) => $query->where('type', $questionnaire->type))->ignore($questionnaire)],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($questionnaire, $data, $request, $activationService): void {
            $values = [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period' => $data['period'] ?? null,
                'estimated_minutes' => $data['estimated_minutes'] ?? null,
                'version' => $data['version'],
                'published_at' => $data['published_at'] ?? $questionnaire->published_at,
            ];

            $questionnaire->update($values);

            if ($data['status'] === 'published' || $request->boolean('is_active')) {
                $activationService->activate($questionnaire);
            } else {
                $questionnaire->update(['status' => $data['status'], 'is_active' => false]);
            }

            $this->audit->log($questionnaire->id, 'questionnaire.updated', "Updated details of \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire, null, $data);
        });

        return redirect()
            ->route($request->input('next') === 'sections' ? 'admin.questionnaires.sections.index' : 'admin.questionnaires.details', $questionnaire)
            ->with('status', 'Details saved.');
    }

    /**
     * Step 3 save: how the overall result is worked out — the scoring
     * method, how sections are weighted (and each weight when custom), and
     * the scale results are reported on.
     */
    public function updateScoring(Request $request, Questionnaire $questionnaire): RedirectResponse
    {
        $data = $request->validate([
            'scoring_method' => ['required', Rule::in(Questionnaire::scoringMethods())],
            'section_weighting' => ['required', Rule::in(Questionnaire::sectionWeightings())],
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['required', 'integer', Rule::exists('questionnaire_sections', 'id')->where('questionnaire_id', $questionnaire->id)],
            'sections.*.category_weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'result_basis' => ['required', Rule::in(['percentage', 'scale'])],
            'result_scale_min' => ['required_if:result_basis,scale', 'nullable', 'integer', 'min:-10000', 'max:10000'],
            'result_scale_max' => ['required_if:result_basis,scale', 'nullable', 'integer', 'min:-10000', 'max:10000', 'gt:result_scale_min'],
        ]);

        DB::transaction(function () use ($questionnaire, $data): void {
            $custom = $data['section_weighting'] === Questionnaire::WEIGHTING_CUSTOM;

            if ($custom) {
                foreach ($data['sections'] ?? [] as $index => $row) {
                    if (! isset($row['category_weight']) || (float) $row['category_weight'] <= 0) {
                        $section = $questionnaire->sections()->find($row['id']);
                        throw ValidationException::withMessages([
                            "sections.{$index}.category_weight" => 'Section "'.($section?->title ?? $index + 1).'" needs a weight above 0, or switch to equal weights.',
                        ]);
                    }
                    $questionnaire->sections()->whereKey($row['id'])->update(['category_weight' => (float) $row['category_weight']]);
                }
            }

            $percentage = $data['result_basis'] === 'percentage';
            $questionnaire->update([
                'scoring_method' => $data['scoring_method'],
                'section_weighting' => $data['section_weighting'],
                'result_scale_min' => $percentage ? 0 : $data['result_scale_min'],
                'result_scale_max' => $percentage ? 100 : $data['result_scale_max'],
            ]);

            $this->audit->log($questionnaire->id, 'questionnaire.updated', "Updated scoring of \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire, null, $data);
        });

        return redirect()
            ->route($request->input('next') === 'results' ? 'admin.questionnaires.result-levels' : 'admin.questionnaires.scoring', $questionnaire)
            ->with('status', 'Scoring saved.');
    }

    /**
     * Step 4 save: the result levels (and the optional stress ranges) with
     * the support recommended for each.
     */
    public function updateRanges(Request $request, Questionnaire $questionnaire, ScaleBandValidator $bandValidator): RedirectResponse
    {
        $data = $request->validate([
            'bands' => ['nullable', 'array'],
            'bands.*.id' => ['nullable', 'integer'],
            'bands.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'bands.*.scope' => ['nullable', Rule::in([StressScoreBand::SCOPE_OVERALL, StressScoreBand::SCOPE_STRESS])],
            'bands.*.label' => ['required', 'string', 'max:100'],
            'bands.*.description' => ['nullable', 'string', 'max:2000'],
            'bands.*.harmony_message' => ['nullable', 'string', 'max:2000'],
            'bands.*.min_score' => ['required', 'integer'],
            'bands.*.max_score' => ['required', 'integer'],
            'bands.*.position' => ['required', 'integer', 'min:0'],
            'bands.*.is_active' => ['nullable', 'boolean'],
            'bands.*.intervention_id' => ['nullable', 'integer', 'exists:interventions,id'],
            'bands.*.intervention_ids' => ['nullable', 'array'],
            'bands.*.intervention_ids.*' => ['nullable', 'integer', 'exists:interventions,id'],
            'result_scale_min' => ['required_with:result_scale_max', 'nullable', 'integer', 'min:-10000', 'max:10000'],
            'result_scale_max' => ['required_with:result_scale_min', 'nullable', 'integer', 'min:-10000', 'max:10000', 'gt:result_scale_min'],
        ]);

        DB::transaction(function () use ($questionnaire, $data, $bandValidator): void {
            if (array_key_exists('result_scale_max', $data) && $data['result_scale_max'] !== null) {
                $questionnaire->update([
                    'result_scale_min' => $data['result_scale_min'] ?? 0,
                    'result_scale_max' => $data['result_scale_max'],
                ]);
            }
            $this->assertBandsWithinScale($questionnaire, $data['bands'] ?? []);
            $this->syncBands($questionnaire, $data['bands'] ?? [], $bandValidator);
            $this->audit->log($questionnaire->id, 'questionnaire.updated', "Updated result levels of \"{$questionnaire->title}\" (v{$questionnaire->version}).", $questionnaire);
        });

        return redirect()
            ->route($request->input('next') === 'review' ? 'admin.questionnaires.review' : 'admin.questionnaires.result-levels', $questionnaire)
            ->with('status', 'Result levels saved.');
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

    /** Reporting and version history hub for Questionnaire Management. */
    public function reports(): View
    {
        $questionnaires = Questionnaire::query()->notInTrash();

        return view('admin.questionnaires.reports', [
            'activeCount' => (clone $questionnaires)->where('is_active', true)->count(),
            'draftCount' => (clone $questionnaires)->where('status', 'draft')->count(),
            'archivedCount' => (clone $questionnaires)->where('status', 'archived')->count(),
            'assessmentCount' => StressAssessment::query()->where('assessment_status', 'completed')->count(),
            'attention' => (clone $questionnaires)->where('status', 'draft')->withCount(['questions', 'sections'])->orderByDesc('updated_at')->limit(5)->get(),
            'recentVersions' => (clone $questionnaires)->withCount(['questions', 'sections'])->orderByDesc('updated_at')->limit(5)->get(),
        ]);
    }

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
     * Copy a questionnaire into a brand-new library questionnaire of its own
     * — sections, questions, scoring and result levels included — and open
     * its Basic info step so it can be renamed.
     */
    public function duplicate(Questionnaire $questionnaire, QuestionnaireVersioner $versioner): RedirectResponse
    {
        $copy = $versioner->duplicate($questionnaire, auth()->id());
        $this->audit->log($copy->id, 'questionnaire.duplicated', "Duplicated \"{$questionnaire->title}\" v{$questionnaire->version} as \"{$copy->title}\".", $copy);

        return redirect()->route('admin.questionnaires.details', $copy)->with(
            'status',
            "\"{$copy->title}\" was created as a draft copy. Rename it, adjust anything you like, then publish it when ready.",
        );
    }

    /**
     * "As a student would see it": the questionnaire rendered with the same
     * controls the app uses. Answers can be submitted for a dry run through
     * the real scoring engine — nothing is saved.
     */
    public function preview(Questionnaire $questionnaire): View
    {
        return view('admin.questionnaires.preview', $this->previewData($questionnaire));
    }

    /**
     * Score a set of preview answers with the real engine and show the
     * result a student would get. Configuration problems surface here as a
     * plain message instead of an error page.
     */
    public function previewSubmit(Request $request, Questionnaire $questionnaire, AssessmentScoringService $scoring, RecommendedInterventionService $recommendations): View
    {
        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable'],
        ]);

        $answers = [];
        foreach ($data['answers'] ?? [] as $questionId => $chosen) {
            if ($chosen === null || $chosen === '' || $chosen === []) {
                continue;
            }
            $answers[(int) $questionId] = is_array($chosen)
                ? array_values(array_map('intval', array_filter($chosen, fn ($v) => $v !== null && $v !== '')))
                : (int) $chosen;
        }
        $answers = array_filter($answers, fn ($v) => $v !== [] && $v !== 0);

        $result = null;
        $error = null;
        try {
            $scored = $scoring->score($questionnaire, $answers);
            $band = $scored['score_band'];
            $result = [
                'total_score' => $scored['total_score'],
                'result_scale' => $questionnaire->resultScale(),
                'percentage' => $scored['overall']['percentage'] ?? null,
                'band' => $band,
                'breakdown' => $scored['breakdown'],
                'recommendations' => $recommendations->forBand($band),
            ];
        } catch (ValidationException $e) {
            $error = collect($e->errors())->flatten()->first();
        }

        return view('admin.questionnaires.preview', $this->previewData($questionnaire) + [
            'previewResult' => $result,
            'previewError' => $error,
            'previewAnswers' => $answers,
        ]);
    }

    /** @return array<string, mixed> */
    private function previewData(Questionnaire $questionnaire): array
    {
        $questionnaire->load([
            'sections' => fn ($q) => $q->where('is_active', true)->orderBy('position')->orderBy('id'),
            'questions' => fn ($q) => $q->where('stress_questions.is_active', true)
                ->orderBy('questionnaire_questions.position')
                ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('position')]),
        ]);

        return [
            'questionnaire' => $questionnaire,
            'questionsBySection' => $questionnaire->questions->groupBy(fn ($q) => $q->pivot->questionnaire_section_id),
        ];
    }

    /**
     * Step 1 — Basic info: name, description, type, estimated time, go-live.
     */
    public function details(Questionnaire $questionnaire, QuestionnaireReview $reviewer): View
    {
        return view('admin.questionnaires.details', [
            'questionnaire' => $questionnaire,
            'review' => $reviewer->run($questionnaire->fresh()),
            'questionCount' => $questionnaire->questions()->count(),
            'resultScale' => $questionnaire->resultScale(),
        ]);
    }

    /**
     * Step 3 — Scoring: the scoring method, section weights and result
     * scale, with every derived figure worked out for the admin.
     */
    public function scoring(Questionnaire $questionnaire, QuestionnaireActivationService $activation, QuestionnaireReview $reviewer, AssessmentScoringService $scoring): View
    {
        $questionnaire->load([
            'sections' => fn ($query) => $query->withCount('questions')->orderBy('position')->orderBy('id'),
            'scoreBands' => fn ($query) => $query->orderBy('scope')->orderBy('position'),
        ]);

        $overview = $scoring->overview($questionnaire);
        $activeQuestions = $questionnaire->questions()->where('stress_questions.is_active', true)->with('options')->get();

        $validationError = null;
        try {
            $activation->preflight($questionnaire);
        } catch (ValidationException $e) {
            $validationError = collect($e->errors())->flatten()->first();
        }

        // A per-question view of the points at stake, so an uneven weight or
        // a reversed scale is visible without opening each question.
        $questionRows = $activeQuestions->map(fn (StressQuestion $q) => [
            'id' => $q->id,
            'section_id' => $q->pivot->questionnaire_section_id,
            'text' => $q->question_text,
            'type' => $q->question_type,
            'answer_mode' => $q->answerMode(),
            'scoring_method' => $q->scoringMethod(),
            'weight' => (float) $q->wellbeing_weight,
            'reversed' => (bool) $q->is_reverse_scored,
            'min' => $q->resolvedMinScore(),
            'max' => $q->resolvedMaxScore(),
        ])->groupBy('section_id');

        return view('admin.questionnaires.scoring', [
            'questionnaire' => $questionnaire,
            'review' => $reviewer->run($questionnaire->fresh()),
            'overview' => $overview,
            'questionRows' => $questionRows,
            'rawSpan' => AssessmentScoringService::possibleTotalRange($activeQuestions),
            'scoreSpan' => $overview['result_span'],
            'resultScale' => $questionnaire->resultScale(),
            'overallBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_OVERALL)->values(),
            'stressBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_STRESS)->values(),
            'validationError' => $validationError,
        ]);
    }

    /**
     * Step 4 — Result levels & recommendations: the ranges the result is
     * matched against and the support recommended for each.
     */
    public function resultLevels(Questionnaire $questionnaire, QuestionnaireReview $reviewer, AssessmentScoringService $scoring): View
    {
        $questionnaire->load(['scoreBands' => fn ($q) => $q->orderBy('scope')->orderBy('position')]);

        $overview = $scoring->overview($questionnaire);
        $scoreSpan = $overview['result_span'];
        $resultScale = $questionnaire->resultScale();
        $activeOverallBands = $questionnaire->scoreBands
            ->where('scope', StressScoreBand::SCOPE_OVERALL)->where('is_active', true)->sortBy('min_score')->values();

        return view('admin.questionnaires.result-levels', [
            'questionnaire' => $questionnaire,
            'review' => $reviewer->run($questionnaire->fresh()),
            'overview' => $overview,
            'questionCount' => $overview['question_count'],
            'rawSpan' => $overview['total_span'],
            'resultScale' => $resultScale,
            'scoreSpan' => $scoreSpan,
            'overallBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_OVERALL)->values(),
            'stressBands' => $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_STRESS)->values(),
            'rangeProblems' => $reviewer->rangeProblems($activeOverallBands, $scoreSpan, $resultScale !== null),
            'interventions' => Intervention::query()->where('is_active', true)->orderBy('content_type')->orderBy('title')->get(['id', 'title', 'content_type']),
            'primaryInterventionByBand' => $reviewer->primaryInterventionByBand($questionnaire),
            'interventionsByBand' => $this->interventionsByBand($questionnaire),
        ]);
    }

    /**
     * Step 5 — Review & Publish: the whole questionnaire checked in one
     * place, with every issue pointing at where to fix it, and the only
     * Publish button.
     */
    public function review(Questionnaire $questionnaire, QuestionnaireReview $reviewer, AssessmentScoringService $scoring): View
    {
        return view('admin.questionnaires.review', [
            'questionnaire' => $questionnaire,
            'review' => $reviewer->run($questionnaire),
            'overview' => $scoring->overview($questionnaire->fresh()),
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
            // The version-family key. Any slug is valid — each questionnaire
            // has its own family so it versions and publishes independently.
            'type' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
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
            // `false` = the field was not in the form at all, so it stays as
            // it is; null / [] = the admin cleared it.
            $primaryId = array_key_exists('intervention_id', $item)
                ? ($item['intervention_id'] === null ? null : (int) $item['intervention_id'])
                : false;
            $additionalIds = array_key_exists('intervention_ids', $item)
                ? array_values(array_map('intval', array_filter((array) $item['intervention_ids'], fn ($v) => $v !== null && $v !== '')))
                : null;
            unset($item['id'], $item['intervention_id'], $item['intervention_ids']);
            $band ??= $questionnaire->scoreBands()->make();
            $band->fill($item + ['created_by_user_id' => $questionnaire->created_by_user_id])->save();
            $retained[] = $band->id;

            if ($primaryId !== false || $additionalIds !== null) {
                $this->setRecommendations($band, $primaryId, $additionalIds);
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
     * The support recommended for a result level: `$primaryId` is shown
     * first, `$additionalIds` follow in the order given. Passing null for
     * either leaves that part as it is; an empty list clears it. Links are
     * deactivated rather than deleted so history keeps its references.
     *
     * @param  array<int, int>|null  $additionalIds
     */
    private function setRecommendations(StressScoreBand $band, int|null|false $primaryId, ?array $additionalIds): void
    {
        $current = $band->recommendations()->where('is_active', true)->orderBy('priority')->orderBy('id')->get();
        $currentPrimary = $current->first()?->intervention_id !== null ? (int) $current->first()->intervention_id : null;
        $currentOthers = $current->skip(1)->pluck('intervention_id')->map(fn ($id) => (int) $id)->all();

        // What was not submitted stays as it is; what was submitted replaces
        // its part of the list. The primary always sorts first. Naming a new
        // first item keeps the old one linked, just no longer first;
        // clearing the first item drops it.
        $primary = $primaryId === false ? $currentPrimary : $primaryId;
        $others = $additionalIds ?? $currentOthers;
        if ($additionalIds === null && $primaryId !== false && $primaryId !== null && $currentPrimary !== null && $currentPrimary !== $primaryId) {
            array_unshift($others, $currentPrimary);
        }

        $desired = array_values(array_unique(array_filter(
            array_merge($primary !== null ? [$primary] : [], $others),
            fn ($id) => $id !== null && $id > 0,
        )));

        foreach ($desired as $priority => $interventionId) {
            $band->recommendations()->updateOrCreate(
                ['intervention_id' => $interventionId],
                ['is_active' => true, 'priority' => $priority],
            );
        }
        // Dropped links are switched off, never deleted, so history keeps them.
        $band->recommendations()
            ->when($desired !== [], fn ($q) => $q->whereNotIn('intervention_id', $desired))
            ->update(['is_active' => false]);
    }

    /**
     * Every support item linked to each level (primary first), keyed by band id.
     *
     * @return array<int, array<int, int>>
     */
    private function interventionsByBand(Questionnaire $questionnaire): array
    {
        $out = [];
        $rows = InterventionRecommendation::query()
            ->whereIn('stress_score_band_id', $questionnaire->scoreBands()->pluck('id'))
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get(['stress_score_band_id', 'intervention_id']);
        foreach ($rows as $row) {
            $out[(int) $row->stress_score_band_id][] = (int) $row->intervention_id;
        }

        return $out;
    }
}
