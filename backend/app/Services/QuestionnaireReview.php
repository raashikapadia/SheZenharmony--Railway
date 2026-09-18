<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\InterventionRecommendation;
use App\Models\Questionnaire;
use App\Models\StressScoreBand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reads the complete questionnaire — every section, question, answer,
 * score, the result scale, every range and every linked support item — and
 * says, in plain words, whether it is ready to publish.
 *
 * The outcome is a short list of checks (one line each, all green when the
 * questionnaire is sound), a list of issues that block publishing, and a
 * list of things worth a look that do not (possible duplicates, ranges with
 * no support item). Nothing here is phrased for a developer: no ids, no
 * field names, and every issue points at the place to fix it.
 */
class QuestionnaireReview
{
    public function __construct(private readonly QuestionnaireActivationService $activation) {}

    /**
     * @return array{
     *     ready: bool,
     *     checks: array<int, array{label: string, ok: bool}>,
     *     issues: array<int, array{where: string, what: string, fix: string}>,
     *     warnings: array<int, array{where: string, what: string, detail: ?string, fix: string}>,
     *     summary: array{sections: int, questions: int, scale: ?array{0: int, 1: int}, raw: array{0: int, 1: int}, ranges: int, interventions: int}
     * }
     */
    public function run(Questionnaire $questionnaire): array
    {
        $questionnaire->loadMissing([
            'sections' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'questions' => fn ($q) => $q->orderBy('questionnaire_questions.position')->with([
                'options' => fn ($o) => $o->orderBy('position'),
            ]),
            'scoreBands' => fn ($q) => $q->orderBy('min_score')->with([
                'recommendations' => fn ($r) => $r->where('is_active', true)->with('intervention'),
            ]),
        ]);

        $issues = [];
        $warnings = [];
        $checks = [];
        $editor = route('admin.questionnaires.sections.index', $questionnaire);
        $details = route('admin.questionnaires.details', $questionnaire);

        $sections = $questionnaire->sections->where('is_active', true)->values();
        $sectionById = $questionnaire->sections->keyBy('id');
        $questions = $questionnaire->questions;
        $bands = $questionnaire->scoreBands
            ->where('scope', StressScoreBand::SCOPE_OVERALL)
            ->where('is_active', true)
            ->sortBy('min_score')
            ->values();

        // ---- Questionnaire -------------------------------------------------
        $detailsOk = trim((string) $questionnaire->title) !== '';
        if (! $detailsOk) {
            $issues[] = ['where' => 'Questionnaire details', 'what' => 'The questionnaire needs a name.', 'fix' => $details];
        }
        $checks[] = ['label' => 'Questionnaire details', 'ok' => $detailsOk];

        // ---- Sections ------------------------------------------------------
        $sectionsOk = true;
        if ($sections->isEmpty()) {
            $sectionsOk = false;
            $issues[] = ['where' => 'Sections', 'what' => 'Add at least one section — questions live inside sections.', 'fix' => $editor];
        }
        foreach ($sections as $section) {
            if (trim((string) $section->title) === '') {
                $sectionsOk = false;
                $issues[] = ['where' => 'Sections', 'what' => 'A section has no name.', 'fix' => route('admin.questionnaires.sections.edit', [$questionnaire, $section])];
            }
        }
        foreach ($this->duplicates($sections, fn ($s) => $s->title) as $group) {
            $warnings[] = [
                'where' => 'Sections',
                'what' => 'Possible duplicate section: "'.$group->first()->title.'" appears '.$group->count().' times.',
                'detail' => null,
                'fix' => $editor,
            ];
        }
        $checks[] = ['label' => $sections->count().' '.Str::plural('section', $sections->count()), 'ok' => $sectionsOk];

        // ---- Questions -----------------------------------------------------
        $questionsOk = true;
        $sectionIds = $sections->pluck('id')->map(fn ($id) => (int) $id);
        foreach ($sections as $section) {
            $inSection = $questions->filter(fn ($q) => (int) $q->pivot->questionnaire_section_id === (int) $section->id);
            if ($inSection->isEmpty()) {
                $questionsOk = false;
                $issues[] = ['where' => 'Section "'.$section->title.'"', 'what' => 'This section has no questions. Add a question, or delete the section.', 'fix' => $editor.'#section-'.$section->id];
            }
        }
        foreach ($questions as $question) {
            $sectionId = $question->pivot->questionnaire_section_id;
            $section = $sectionId ? $sectionById->get($sectionId) : null;
            $where = $section ? 'Section "'.$section->title.'"' : 'Questions';
            $number = $this->questionNumber($questions, $question);
            $fix = $section
                ? route('admin.questionnaires.sections.questions.edit', [$questionnaire, $section, $question])
                : $editor;

            if ($sectionId === null || ! $sectionIds->contains((int) $sectionId)) {
                $questionsOk = false;
                $issues[] = ['where' => 'Questions', 'what' => "\"{$this->short($question->question_text)}\" is not inside a section. Every question needs one.", 'fix' => $editor];
            }
            if (trim((string) $question->question_text) === '') {
                $questionsOk = false;
                $issues[] = ['where' => $where, 'what' => "Question {$number} has no wording.", 'fix' => $fix];
            }
            if (! $question->is_active) {
                $questionsOk = false;
                $issues[] = ['where' => $where, 'what' => "Question {$number} is hidden from students. Show it, or remove it from the questionnaire.", 'fix' => $fix];
            }
        }
        foreach ($this->duplicates($questions, fn ($q) => $q->question_text) as $group) {
            $places = $group->map(function ($q) use ($sectionById) {
                $section = $sectionById->get($q->pivot->questionnaire_section_id);

                return $section ? 'Section '.$section->position.' — '.$section->title : 'Not in a section';
            })->unique()->values();
            $warnings[] = [
                'where' => 'Possible duplicate question',
                'what' => '"'.$group->first()->question_text.'" appears '.$group->count().' times.',
                'detail' => 'Appears in: '.$places->join(' · '),
                'fix' => $editor,
            ];
        }
        $checks[] = ['label' => $questions->count().' '.Str::plural('question', $questions->count()), 'ok' => $questionsOk && $questions->isNotEmpty()];
        if ($questions->isEmpty() && $sections->isNotEmpty()) {
            $questionsOk = false;
        }

        // ---- Answers -------------------------------------------------------
        $answersOk = true;
        foreach ($questions as $question) {
            $section = $sectionById->get($question->pivot->questionnaire_section_id);
            $where = $section ? 'Section "'.$section->title.'"' : 'Questions';
            $number = $this->questionNumber($questions, $question);
            $fix = $section
                ? route('admin.questionnaires.sections.questions.edit', [$questionnaire, $section, $question])
                : $editor;
            $options = $question->options->where('is_active', true)->values();

            if ($options->count() < 2) {
                $answersOk = false;
                $issues[] = ['where' => $where, 'what' => "Question {$number} needs at least two answer options.", 'fix' => $fix];

                continue;
            }
            if ($options->contains(fn ($o) => trim((string) $o->label) === '')) {
                $answersOk = false;
                $issues[] = ['where' => $where, 'what' => "Question {$number} has an answer with no wording.", 'fix' => $fix];
            }
            if ($options->contains(fn ($o) => $o->score === null)) {
                $answersOk = false;
                $issues[] = ['where' => $where, 'what' => "Question {$number} has an answer with no points.", 'fix' => $fix];
            }
            if ($this->duplicates($options, fn ($o) => $o->value)->isNotEmpty()) {
                $answersOk = false;
                $issues[] = ['where' => $where, 'what' => "Question {$number} has two answers that are the same. Remove one.", 'fix' => $fix];
            } elseif ($this->duplicates($options, fn ($o) => $o->label)->isNotEmpty()) {
                $warnings[] = ['where' => $where, 'what' => "Question {$number} has two answers with the same wording.", 'detail' => null, 'fix' => $fix];
            }
        }
        $checks[] = ['label' => 'Answers configured', 'ok' => $answersOk && $questions->isNotEmpty()];

        // ---- Scoring -------------------------------------------------------
        $activeQuestions = $questions->filter(fn ($q) => $q->is_active);
        $raw = $sections->isNotEmpty()
            ? AssessmentScoringService::possibleTotalRange($activeQuestions)
            : AssessmentScoringService::flatTotalRange($activeQuestions);
        $scoringOk = $answersOk && $questions->isNotEmpty() && $raw[1] > $raw[0];
        if ($answersOk && $questions->isNotEmpty() && $raw[1] <= $raw[0]) {
            $issues[] = ['where' => 'Scoring', 'what' => 'Every possible total comes out the same, so results cannot be placed on the scale. Give the answers different points.', 'fix' => $editor];
        }
        $checks[] = ['label' => $scoringOk ? "Scoring configured (raw score {$raw[0]}–{$raw[1]})" : 'Scoring configured', 'ok' => $scoringOk];

        // ---- Result scale --------------------------------------------------
        $scale = $questionnaire->resultScale();
        $scaleOk = $scale !== null && $scale[1] > $scale[0];
        if ($scale === null) {
            $issues[] = ['where' => 'Result scale', 'what' => 'Enter the client\'s result scale (its minimum and maximum) so results can be reported on it.', 'fix' => $details.'#ranges'];
        } elseif ($scale[1] <= $scale[0]) {
            $issues[] = ['where' => 'Result scale', 'what' => 'The scale\'s maximum must be higher than its minimum.', 'fix' => $details.'#ranges'];
        }
        $checks[] = ['label' => $scaleOk ? "Result scale: {$scale[0]}–{$scale[1]}" : 'Result scale', 'ok' => $scaleOk];

        // ---- Result ranges -------------------------------------------------
        $span = $scaleOk ? $scale : $raw;
        // Outside a configured client scale is a mistake; outside a raw span is
        // merely slack, which the coverage rule below tolerates.
        $rangeProblems = $this->rangeProblems($bands, $span, $scaleOk);
        foreach ($rangeProblems as $problem) {
            $issues[] = ['where' => 'Result ranges', 'what' => $problem, 'fix' => $details.'#ranges'];
        }
        $checks[] = ['label' => $bands->count().' result '.Str::plural('range', $bands->count()).' configured', 'ok' => $rangeProblems === [] && $bands->isNotEmpty()];

        // ---- Interventions -------------------------------------------------
        $interventionsOk = true;
        $allLevels = Intervention::query()->where('is_active', true)
            ->whereDoesntHave('recommendations', fn ($r) => $r->where('is_active', true))
            ->count();
        $linkedIds = collect();
        foreach ($bands as $band) {
            $links = $band->recommendations;
            $live = $links->filter(fn ($link) => $link->intervention && $link->intervention->is_active);
            $linkedIds = $linkedIds->merge($live->pluck('intervention_id'));
            if ($links->count() > $live->count()) {
                $interventionsOk = false;
                $warnings[] = ['where' => 'Result ranges', 'what' => "\"{$band->label}\" points to a support item that no longer exists or is switched off.", 'detail' => null, 'fix' => $details.'#ranges'];
            }
            if ($live->isEmpty() && $allLevels === 0) {
                $interventionsOk = false;
                $warnings[] = ['where' => 'Result ranges', 'what' => "\"{$band->label}\" has no support item to recommend.", 'detail' => null, 'fix' => $details.'#ranges'];
            }
        }
        $interventionCount = $linkedIds->unique()->count() + $allLevels;
        $checks[] = ['label' => 'Interventions configured', 'ok' => $interventionsOk && $bands->isNotEmpty()];

        // ---- Backstop: the same rules publishing enforces --------------------
        // Anything they catch that the checks above missed is still shown in
        // their own plain words, so the admin never meets a surprise.
        if ($issues === []) {
            try {
                $this->activation->preflight($questionnaire->fresh());
            } catch (ValidationException $e) {
                $issues[] = ['where' => 'Questionnaire', 'what' => (string) collect($e->errors())->flatten()->first(), 'fix' => $editor];
            }
        }

        return [
            'ready' => $issues === [],
            'checks' => $checks,
            'issues' => $issues,
            'warnings' => $warnings,
            'summary' => [
                'sections' => $sections->count(),
                'questions' => $questions->count(),
                'scale' => $scale,
                'raw' => $raw,
                'ranges' => $bands->count(),
                'interventions' => $interventionCount,
            ],
        ];
    }

    /**
     * The lowest and highest raw points total the questionnaire can produce,
     * from its active questions' answer points — the same arithmetic the
     * scorer and the publish check use.
     *
     * @return array{0: int, 1: int}
     */
    public function rawSpan(Questionnaire $questionnaire): array
    {
        $questions = $questionnaire->questions()
            ->where('stress_questions.is_active', true)
            ->with(['options' => fn ($o) => $o->where('is_active', true)])
            ->get();

        return $questionnaire->sections()->where('is_active', true)->exists()
            ? AssessmentScoringService::possibleTotalRange($questions)
            : AssessmentScoringService::flatTotalRange($questions);
    }

    /**
     * The support item shown first for each range — the active
     * recommendation with the lowest priority, keyed by band id.
     *
     * @return array<int, int>
     */
    public function primaryInterventionByBand(Questionnaire $questionnaire): array
    {
        $primary = [];
        $rows = InterventionRecommendation::query()
            ->whereIn('stress_score_band_id', $questionnaire->scoreBands()->pluck('id'))
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get(['stress_score_band_id', 'intervention_id']);
        foreach ($rows as $row) {
            $primary[(int) $row->stress_score_band_id] ??= (int) $row->intervention_id;
        }

        return $primary;
    }

    /**
     * Plain-language problems with the overall ranges against the span they
     * must cover: backwards ranges, overlaps, ranges outside the span, and
     * gaps. Empty when all is well.
     *
     * @param  Collection<int, StressScoreBand>  $bands  sorted by min_score
     * @param  array{0: int, 1: int}  $span
     * @return array<int, string>
     */
    public function rangeProblems(Collection $bands, array $span, bool $strictBounds = true): array
    {
        [$from, $to] = $span;
        if ($bands->isEmpty()) {
            return ["No result ranges yet — add ranges that together cover {$from}–{$to}."];
        }

        $problems = [];
        $cursor = $from;
        $previous = null;
        foreach ($bands as $band) {
            if ($band->min_score > $band->max_score) {
                $problems[] = "\"{$band->label}\" runs from {$band->min_score} to {$band->max_score} — the minimum can't be higher than the maximum.";
            }
            if ($strictBounds && ($band->min_score < $from || $band->max_score > $to)) {
                $problems[] = "\"{$band->label}\" ({$band->min_score}–{$band->max_score}) falls outside the result scale {$from}–{$to}.";
            }
            if ($previous !== null && $band->min_score <= $previous->max_score) {
                $problems[] = "\"{$previous->label}\" ({$previous->min_score}–{$previous->max_score}) and \"{$band->label}\" ({$band->min_score}–{$band->max_score}) overlap.";
            }
            if ($band->min_score > $cursor) {
                $gapEnd = $band->min_score - 1;
                $problems[] = $gapEnd === $cursor
                    ? "Score {$cursor} is not covered by any range."
                    : "Scores {$cursor}–{$gapEnd} are not covered by any range.";
            }
            $cursor = max($cursor, $band->max_score + 1);
            $previous = $band;
        }
        if ($cursor <= $to) {
            $problems[] = $cursor === $to
                ? "Score {$to} is not covered by any range."
                : "Scores {$cursor}–{$to} are not covered by any range.";
        }

        return $problems;
    }

    /**
     * Groups of items whose `$key` matches once trimmed and lower-cased.
     *
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @param  callable(T): ?string  $key
     * @return Collection<int, Collection<int, T>>
     */
    private function duplicates(Collection $items, callable $key): Collection
    {
        return $items
            ->groupBy(fn ($item) => Str::of((string) $key($item))->squish()->lower()->toString())
            ->filter(fn ($group, $k) => $k !== '' && $group->count() > 1)
            ->values();
    }

    private function questionNumber(Collection $questions, $question): int
    {
        return (int) $questions->search(fn ($q) => $q->id === $question->id) + 1;
    }

    private function short(string $text): string
    {
        return Str::limit(trim($text), 60);
    }
}
