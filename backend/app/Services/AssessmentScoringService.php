<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\QuestionOption;
use App\Models\StressQuestion;
use App\Models\StressScoreBand;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The single authoritative scoring engine for SheZen questionnaires.
 *
 * Everything it does is read from stored configuration — nothing in here
 * knows about a particular instrument, scale, level name or section count:
 *
 *   answer(s)  →  question scoring config  →  question score
 *              →  section raw / min / max  →  section percentage × weight
 *              →  questionnaire scoring method  →  total, maximum, percentage
 *              →  result on the result scale  →  configured result level
 *
 * Two overall strategies, chosen by `questionnaires.scoring_method`:
 *
 *  - **points_total**: the total is the sum of every question's scored
 *    points (reverse scoring and question weights applied), normalised onto
 *    the result scale. Section weights only shape the per-section
 *    breakdown. This is the historical behaviour and the default for
 *    questionnaires that exist already.
 *  - **weighted_sections**: each section contributes
 *    (raw ÷ maximum) × weight, the total is the sum of those contributions
 *    and the maximum is the sum of the weights. With equal weighting the
 *    engine derives the weights itself so the admin never types a number.
 *
 * A questionnaire with no active sections scores flat: one points total
 * matched against its `overall` result ranges, exactly as before sections
 * existed.
 */
class AssessmentScoringService
{
    /**
     * @param  array<int, int|array<int, int>>  $answers  question id => chosen option id, or list of option ids
     * @return array{
     *     total_score: int,
     *     score_band: StressScoreBand,
     *     breakdown: array<string, mixed>|null,
     *     config_snapshot: array<string, mixed>|null,
     *     responses: array<int, array<string, mixed>>
     * }
     */
    public function score(Questionnaire $questionnaire, array $answers): array
    {
        $questionnaire->load([
            'sections' => fn ($query) => $query->where('is_active', true)->orderBy('position')->orderBy('id'),
            'questions' => fn ($query) => $query->where('stress_questions.is_active', true)
                ->with(['options' => fn ($options) => $options->where('is_active', true)->orderBy('position')]),
        ]);

        /** @var Collection<int, StressQuestion> $questions */
        $questions = $questionnaire->questions->keyBy('id');

        $this->assertAnswersBelong($questions, $answers);
        $this->assertRequiredAnswered($questions, $answers);

        // Resolve every answer to its option(s) and score the question once.
        $resolved = [];
        $flatTotal = 0;
        foreach ($answers as $questionId => $chosen) {
            /** @var StressQuestion $question */
            $question = $questions->get($questionId);
            $resolved[$questionId] = $this->scoreQuestion($question, $chosen);
            $flatTotal += $resolved[$questionId]['raw'];
        }

        $activeSections = $questionnaire->sections;

        if ($activeSections->isEmpty()) {
            // Same rule as the sectioned path: the raw total is normalised
            // onto the result scale when one is configured.
            [$rawMin, $rawMax] = self::flatTotalRange($questionnaire->questions);
            $result = self::normalise((float) $flatTotal, $rawMin, $rawMax, $questionnaire->resultScale());

            return [
                'total_score' => $result,
                'score_band' => $this->matchBand($questionnaire, StressScoreBand::SCOPE_OVERALL, $result),
                'breakdown' => null,
                'config_snapshot' => null,
                'responses' => $this->flatResponses($resolved),
            ];
        }

        return $this->scoreSectioned($questionnaire, $activeSections, $resolved, $flatTotal);
    }

    // ------------------------------------------------------------------
    // Question level
    // ------------------------------------------------------------------

    /**
     * Turn one answer into points using the question's own configuration:
     * how many options may be chosen, how the chosen options become points,
     * and which way the scale runs.
     *
     * @param  int|array<int, int>  $chosen
     * @return array{question: StressQuestion, option: QuestionOption|null, options: Collection<int, QuestionOption>, raw: int, scored_value: int, min: int, max: int}
     */
    private function scoreQuestion(StressQuestion $question, int|array $chosen): array
    {
        $optionIds = collect(is_array($chosen) ? $chosen : [$chosen])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($optionIds->isEmpty()) {
            throw ValidationException::withMessages(['answers' => 'An answer has no option selected.']);
        }

        if (! $question->allowsMultipleAnswers() && $optionIds->count() !== 1) {
            throw ValidationException::withMessages([
                'answers' => "\"{$this->short($question->question_text)}\" takes exactly one answer.",
            ]);
        }

        $limit = $question->selectionLimit();
        if ($optionIds->count() > $limit) {
            throw ValidationException::withMessages([
                'answers' => "\"{$this->short($question->question_text)}\" allows at most {$limit} ".($limit === 1 ? 'answer' : 'answers').'.',
            ]);
        }

        /** @var Collection<int, QuestionOption> $options */
        $options = $optionIds->map(fn (int $id) => $question->options->firstWhere('id', $id));
        if ($options->contains(null) || $options->contains(fn ($option) => $option->score === null)) {
            throw ValidationException::withMessages([
                'answers' => 'An answer option is invalid, inactive, or not scorable.',
            ]);
        }
        // The transcript lists ticked options in the order the admin laid
        // them out, whatever order the client sent them in.
        $options = $options->sortBy(fn (QuestionOption $option) => [(int) $option->position, (int) $option->id])->values();

        $scores = $options->map(fn (QuestionOption $option) => (int) $option->score);
        $raw = match ($question->scoringMethod()) {
            StressQuestion::SCORING_COUNT_SELECTED => $options->count(),
            StressQuestion::SCORING_MAX_SELECTED => (int) $scores->max(),
            default => (int) $scores->sum(),
        };

        $min = $question->resolvedMinScore() ?? $raw;
        $max = $question->resolvedMaxScore() ?? $raw;

        // "Higher answer = lower score": mirror the points within the
        // question's own range, so 1–5 flips 5→1 and 0–4 flips 4→0.
        $scoredValue = $question->is_reverse_scored ? ($max + $min - $raw) : $raw;

        return [
            'question' => $question,
            'option' => $question->allowsMultipleAnswers() ? null : $options->first(),
            'options' => $options->values(),
            'raw' => $raw,
            'scored_value' => $scoredValue,
            'min' => $min,
            'max' => $max,
        ];
    }

    // ------------------------------------------------------------------
    // Section and questionnaire level
    // ------------------------------------------------------------------

    /**
     * @param  Collection<int, QuestionnaireSection>  $sections
     * @param  array<int, array<string, mixed>>  $resolved
     */
    private function scoreSectioned(Questionnaire $questionnaire, Collection $sections, array $resolved, int $flatTotal): array
    {
        $weights = self::effectiveSectionWeights($questionnaire, $sections);

        $categories = [];
        $overallRaw = 0.0;
        $overallRawMin = 0.0;
        $overallRawMax = 0.0;
        $overallWeighted = 0.0;
        $overallMaxWeighted = 0.0;
        $overallMinWeighted = 0.0;
        $responses = [];

        foreach ($sections as $section) {
            $weight = $weights[(int) $section->id] ?? 0.0;
            $overallMaxWeighted += $weight;

            $raw = 0.0;
            $minPossible = 0.0;
            $maxPossible = 0.0;
            $answeredInSection = 0;

            foreach ($resolved as $questionId => $pair) {
                /** @var StressQuestion $question */
                $question = $pair['question'];
                if ((int) $question->pivot->questionnaire_section_id !== (int) $section->id) {
                    continue;
                }

                $questionWeight = (float) $question->wellbeing_weight;
                $raw += $pair['scored_value'] * $questionWeight;
                $minPossible += $pair['min'] * $questionWeight;
                $maxPossible += $pair['max'] * $questionWeight;
                $answeredInSection++;

                $responses[$questionId] = $pair;
            }

            // Section score as a share of its maximum possible points, then
            // scaled by the section weight — a 10-question, all-max section
            // (raw 50 / max 50) contributes its full weight, "all neutral"
            // (raw 30 / max 50) contributes 60% of it.
            $percentage = $maxPossible > 0
                ? $this->clampPercent($raw / $maxPossible * 100)
                : 0.0;
            $weightedScore = ($percentage / 100) * $weight;
            $minWeighted = $maxPossible > 0 ? ($minPossible / $maxPossible) * $weight : 0.0;

            $overallRaw += $raw;
            $overallRawMin += $minPossible;
            $overallRawMax += $maxPossible;
            $overallWeighted += $weightedScore;
            $overallMinWeighted += $minWeighted;

            $categories[] = [
                'section_id' => (int) $section->id,
                'title' => $section->title,
                'answered_questions' => $answeredInSection,
                'raw_score' => round($raw, 2),
                'min_possible_score' => round($minPossible, 2),
                'max_possible_score' => round($maxPossible, 2),
                'percentage' => round($percentage, 2),
                'category_weight' => round($weight, 2),
                'weighted_score' => round($weightedScore, 2),
            ];
        }

        // Any question left in $resolved but not matched to a section is a
        // configuration error activation should have blocked; fold it into
        // responses so the transcript stays complete.
        foreach ($resolved as $questionId => $pair) {
            $responses[$questionId] ??= $pair;
        }

        $overallPercentage = $overallMaxWeighted > 0
            ? $this->clampPercent($overallWeighted / $overallMaxWeighted * 100)
            : 0.0;

        // The overall strategy decides what "the total" is and the span it
        // can take; the result scale (when configured) is what the total is
        // reported on and what the result levels are written against.
        if ($questionnaire->usesWeightedSections()) {
            $total = $overallWeighted;
            $totalMin = 0.0;
            $totalMax = $overallMaxWeighted;
            [$rawMin, $rawMax] = [0, (int) round($overallMaxWeighted)];
            $overallTotal = self::normaliseFloat($total, $totalMin, $totalMax, $questionnaire->resultScale());
        } else {
            [$rawMin, $rawMax] = self::possibleTotalRange($questionnaire->questions);
            $total = $overallRaw;
            $totalMin = (float) $rawMin;
            $totalMax = (float) $rawMax;
            $overallTotal = self::normalise($overallRaw, $rawMin, $rawMax, $questionnaire->resultScale());
        }

        $wellbeingBand = $this->matchBand($questionnaire, StressScoreBand::SCOPE_OVERALL, (float) $overallTotal);

        $stress = $this->scoreStress($resolved);
        $stressBand = null;
        if ($stress !== null) {
            $stressBand = $this->matchBand($questionnaire, StressScoreBand::SCOPE_STRESS, (float) round($stress['score']));
        }

        return [
            'total_score' => $overallTotal,
            'score_band' => $wellbeingBand,
            'breakdown' => [
                'method' => $questionnaire->scoringMethod(),
                'section_weighting' => $questionnaire->usesEqualSectionWeights() ? Questionnaire::WEIGHTING_EQUAL : Questionnaire::WEIGHTING_CUSTOM,
                'categories' => $categories,
                'overall' => [
                    'raw_score' => round($overallRaw, 2),
                    'raw_min' => $rawMin,
                    'raw_max' => $rawMax,
                    'total' => round($total, 2),
                    'total_min' => round($totalMin, 2),
                    'total_max' => round($totalMax, 2),
                    'result_score' => $overallTotal,
                    'result_scale' => $questionnaire->resultScale(),
                    'weighted_score' => round($overallWeighted, 2),
                    'max_weighted_score' => round($overallMaxWeighted, 2),
                    'percentage' => round($overallPercentage, 2),
                    'band' => [
                        'code' => $wellbeingBand->code,
                        'label' => $wellbeingBand->label,
                        'description' => $wellbeingBand->description,
                        'message' => $wellbeingBand->harmony_message,
                    ],
                ],
                'stress' => $stress === null ? null : [
                    'score' => round($stress['score'], 2),
                    'percentage' => round($stress['score'], 2),
                    'band' => $stressBand ? ['code' => $stressBand->code, 'label' => $stressBand->label] : null,
                ],
                'flat_total' => $flatTotal,
            ],
            'config_snapshot' => $this->buildConfigSnapshot($questionnaire, $sections, $weights, $resolved),
            'responses' => $responses,
            'wellbeing_band' => $wellbeingBand,
            'stress_band' => $stressBand,
            'stress_result' => $stress,
            'overall' => [
                'raw' => round($overallRaw, 2),
                'weighted' => round($overallWeighted, 2),
                'max_weighted' => round($overallMaxWeighted, 2),
                'percentage' => round($overallPercentage, 2),
            ],
        ];
    }

    /**
     * 0-100 stress sub-score from stress-relevant answered questions, or null
     * when the questionnaire has none. Uses the raw response position within
     * the question's range, flipped for "higher answer = less stress".
     *
     * @param  array<int, array<string, mixed>>  $resolved
     * @return array{score: float, contributing: int}|null
     */
    private function scoreStress(array $resolved): ?array
    {
        $numerator = 0.0;
        $denominator = 0.0;
        $contributing = 0;

        foreach ($resolved as $pair) {
            /** @var StressQuestion $question */
            $question = $pair['question'];
            if (! $question->stress_relevant) {
                continue;
            }

            $raw = (int) $pair['raw'];
            $qMin = (int) $pair['min'];
            $qMax = (int) $pair['max'];

            $fraction = $qMax > $qMin ? ($raw - $qMin) / ($qMax - $qMin) : 0.0;
            if ($question->stress_direction === StressQuestion::STRESS_DIRECTION_LESS) {
                $fraction = 1 - $fraction;
            }

            $weight = (float) $question->stress_weight;
            $numerator += $fraction * $weight;
            $denominator += $weight;
            $contributing++;
        }

        if ($contributing === 0 || $denominator <= 0) {
            return null;
        }

        return ['score' => $this->clampPercent($numerator / $denominator * 100), 'contributing' => $contributing];
    }

    // ------------------------------------------------------------------
    // Validation of the submitted answers
    // ------------------------------------------------------------------

    /**
     * @param  Collection<int, StressQuestion>  $questions
     * @param  array<int, mixed>  $answers
     */
    private function assertAnswersBelong(Collection $questions, array $answers): void
    {
        $unknown = collect(array_keys($answers))->diff($questions->keys());
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => 'An answer references a question outside this questionnaire.',
            ]);
        }
    }

    /**
     * @param  Collection<int, StressQuestion>  $questions
     * @param  array<int, mixed>  $answers
     */
    private function assertRequiredAnswered(Collection $questions, array $answers): void
    {
        $missing = $questions->filter(
            fn (StressQuestion $question) => $question->pivot->is_required && ! array_key_exists($question->id, $answers),
        );

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => 'All required questionnaire questions must be answered.',
            ]);
        }
    }

    private function matchBand(Questionnaire $questionnaire, string $scope, float $score): StressScoreBand
    {
        $band = $questionnaire->scoreBands()
            ->where('scope', $scope)
            ->where('is_active', true)
            ->where('min_score', '<=', $score)
            ->where('max_score', '>=', $score)
            ->orderBy('position')
            ->first();

        if (! $band) {
            $label = $scope === StressScoreBand::SCOPE_STRESS ? 'stress' : 'overall';
            throw ValidationException::withMessages([
                'score_bands' => "No active {$label} result level is configured for score ".round($score, 2).'.',
            ]);
        }

        return $band;
    }

    /**
     * @param  array<int, array<string, mixed>>  $resolved
     * @return array<int, array<string, mixed>>
     */
    private function flatResponses(array $resolved): array
    {
        $out = [];
        foreach ($resolved as $questionId => $pair) {
            // Flat questionnaires never applied reverse scoring; the points
            // total is the plain sum of chosen points.
            $out[$questionId] = array_merge($pair, ['scored_value' => $pair['raw']]);
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Ranges, spans and weights shared with validation and admin screens
    // ------------------------------------------------------------------

    /**
     * The weight each active section actually scores with, keyed by section
     * id. Custom weighting reads the stored weight; equal weighting shares
     * 100 evenly so the weights read as percentages and always sum to 100.
     *
     * @param  Collection<int, QuestionnaireSection>  $sections  active sections
     * @return array<int, float>
     */
    public static function effectiveSectionWeights(Questionnaire $questionnaire, Collection $sections): array
    {
        $weights = [];
        $count = $sections->count();
        foreach ($sections as $section) {
            $weights[(int) $section->id] = $questionnaire->usesEqualSectionWeights() && $count > 0
                ? round(100 / $count, 4)
                : (float) $section->category_weight;
        }

        return $weights;
    }

    /**
     * The lowest and highest points total a sectioned questionnaire can
     * produce, from its questions' answer points and weights — the span the
     * points-total strategy normalises. Optional questions can be skipped,
     * so they only widen the span, never narrow it.
     *
     * @param  Collection<int, StressQuestion>  $questions  with options loaded and the questionnaire pivot
     * @return array{0: int, 1: int}
     */
    public static function possibleTotalRange(Collection $questions): array
    {
        $min = 0.0;
        $max = 0.0;
        foreach ($questions as $question) {
            $qMin = $question->resolvedMinScore();
            $qMax = $question->resolvedMaxScore();
            if ($qMin === null || $qMax === null) {
                continue;
            }
            $weight = (float) ($question->wellbeing_weight ?? 1);
            $low = min($qMin, $qMax) * $weight;
            $high = max($qMin, $qMax) * $weight;
            $required = (bool) ($question->pivot->is_required ?? true);
            $min += $required ? $low : min(0.0, $low);
            $max += max(0.0, $high);
        }

        return [(int) round($min), (int) round($max)];
    }

    /**
     * The raw span of a flat (section-less) questionnaire: plain option
     * points, no weights or reverse scoring — matching how it is totalled.
     *
     * @param  Collection<int, StressQuestion>  $questions  with options loaded and the questionnaire pivot
     * @return array{0: int, 1: int}
     */
    public static function flatTotalRange(Collection $questions): array
    {
        $min = 0;
        $max = 0;
        foreach ($questions as $question) {
            $range = $question->optionScoreRange();
            if ($range === null) {
                continue;
            }
            [$qMin, $qMax] = $range;
            $required = (bool) ($question->pivot->is_required ?? true);
            $min += $required ? $qMin : min(0, $qMin);
            $max += max(0, $qMax);
        }

        return [$min, $max];
    }

    /**
     * The span of totals this questionnaire's strategy can produce with the
     * given active questions: the sum of section weights for weighted
     * sections, otherwise the points span.
     *
     * @param  Collection<int, StressQuestion>  $questions  active questions with options and pivot
     * @return array{0: int, 1: int}
     */
    public static function totalSpan(Questionnaire $questionnaire, Collection $questions): array
    {
        $sections = $questionnaire->relationLoaded('sections')
            ? $questionnaire->sections->where('is_active', true)->values()
            : $questionnaire->sections()->where('is_active', true)->get();

        if ($sections->isEmpty()) {
            return self::flatTotalRange($questions);
        }
        if ($questionnaire->usesWeightedSections()) {
            $weights = self::effectiveSectionWeights($questionnaire, $sections);

            return [0, (int) round(array_sum($weights))];
        }

        return self::possibleTotalRange($questions);
    }

    /**
     * The span the overall result levels must cover: the result scale when
     * one is configured, otherwise the strategy's total span.
     *
     * @param  Collection<int, StressQuestion>  $questions  with options loaded and the questionnaire pivot
     * @return array{0: int, 1: int}
     */
    public static function resultSpan(Questionnaire $questionnaire, Collection $questions): array
    {
        return $questionnaire->resultScale() ?? self::totalSpan($questionnaire, $questions);
    }

    /**
     * Map a raw total onto the result scale, e.g. 111 of 0–148 on a 0–40
     * scale → 30. Returns the rounded raw total when there is no scale, and
     * the scale's minimum when the raw span is empty.
     *
     * @param  array{0: int, 1: int}|null  $scale
     */
    public static function normalise(float $raw, int $rawMin, int $rawMax, ?array $scale): int
    {
        return self::normaliseFloat($raw, (float) $rawMin, (float) $rawMax, $scale);
    }

    /**
     * @param  array{0: int, 1: int}|null  $scale
     */
    public static function normaliseFloat(float $value, float $min, float $max, ?array $scale): int
    {
        if ($scale === null) {
            return (int) round($value);
        }
        [$scaleMin, $scaleMax] = $scale;
        if ($max <= $min) {
            return $scaleMin;
        }
        $fraction = max(0.0, min(1.0, ($value - $min) / ($max - $min)));

        return (int) round($fraction * ($scaleMax - $scaleMin) + $scaleMin);
    }

    /**
     * Every figure the admin should never have to work out by hand: per
     * section question counts, point spans and effective weights; the
     * questionnaire's total span, result scale and percentage range. All
     * derived live from the current configuration.
     *
     * @return array{
     *     method: string,
     *     section_weighting: string,
     *     section_count: int,
     *     question_count: int,
     *     sections: array<int, array{id: int, title: string, questions: int, min_raw: float, max_raw: float, weight: float, weight_share: float}>,
     *     weight_total: float,
     *     total_span: array{0: int, 1: int},
     *     result_scale: array{0: int, 1: int}|null,
     *     result_span: array{0: int, 1: int},
     *     percentage_range: array{0: float, 1: float},
     *     question_types: array<string, int>
     * }
     */
    public function overview(Questionnaire $questionnaire): array
    {
        $questionnaire->loadMissing([
            'sections' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'questions' => fn ($q) => $q->where('stress_questions.is_active', true)
                ->orderBy('questionnaire_questions.position')
                ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('position')]),
        ]);

        $sections = $questionnaire->sections->where('is_active', true)->values();
        $questions = $questionnaire->questions;
        $weights = self::effectiveSectionWeights($questionnaire, $sections);
        $weightTotal = array_sum($weights);

        $rows = [];
        $minWeighted = 0.0;
        foreach ($sections as $section) {
            $inSection = $questions->filter(fn ($q) => (int) $q->pivot->questionnaire_section_id === (int) $section->id);
            $minRaw = 0.0;
            $maxRaw = 0.0;
            foreach ($inSection as $question) {
                $qMin = $question->resolvedMinScore();
                $qMax = $question->resolvedMaxScore();
                if ($qMin === null || $qMax === null) {
                    continue;
                }
                $w = (float) ($question->wellbeing_weight ?? 1);
                $minRaw += min($qMin, $qMax) * $w;
                $maxRaw += max($qMin, $qMax) * $w;
            }
            $weight = $weights[(int) $section->id] ?? 0.0;
            if ($maxRaw > 0) {
                $minWeighted += ($minRaw / $maxRaw) * $weight;
            }
            $rows[] = [
                'id' => (int) $section->id,
                'title' => (string) $section->title,
                'questions' => $inSection->count(),
                'min_raw' => round($minRaw, 2),
                'max_raw' => round($maxRaw, 2),
                'weight' => round($weight, 2),
                'weight_share' => $weightTotal > 0 ? round($weight / $weightTotal * 100, 1) : 0.0,
            ];
        }

        $totalSpan = self::totalSpan($questionnaire, $questions);
        $resultScale = $questionnaire->resultScale();

        // The lowest and highest overall percentage a student can score.
        if ($sections->isEmpty()) {
            [$lo, $hi] = $totalSpan;
            $range = $hi > 0 ? [round(max(0, $lo) / $hi * 100, 1), 100.0] : [0.0, 0.0];
        } else {
            $range = $weightTotal > 0 ? [round($minWeighted / $weightTotal * 100, 1), 100.0] : [0.0, 0.0];
        }

        $types = [];
        foreach ($questions as $question) {
            $key = (string) $question->question_type;
            $types[$key] = ($types[$key] ?? 0) + 1;
        }

        return [
            'method' => $questionnaire->scoringMethod(),
            'section_weighting' => $questionnaire->usesEqualSectionWeights() ? Questionnaire::WEIGHTING_EQUAL : Questionnaire::WEIGHTING_CUSTOM,
            'section_count' => $sections->count(),
            'question_count' => $questions->count(),
            'sections' => $rows,
            'weight_total' => round($weightTotal, 2),
            'total_span' => $totalSpan,
            'result_scale' => $resultScale,
            'result_span' => $resultScale ?? $totalSpan,
            'percentage_range' => $range,
            'question_types' => $types,
        ];
    }

    private function clampPercent(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }

    private function short(string $text): string
    {
        return mb_strlen($text) > 60 ? mb_substr(trim($text), 0, 57).'…' : trim($text);
    }

    /**
     * Immutable copy of every scoring rule this result depended on, so the
     * stored result can be explained later without reading live
     * configuration that may since have changed.
     *
     * @param  Collection<int, QuestionnaireSection>  $sections
     * @param  array<int, float>  $weights
     * @param  array<int, array<string, mixed>>  $resolved
     * @return array<string, mixed>
     */
    private function buildConfigSnapshot(Questionnaire $questionnaire, Collection $sections, array $weights, array $resolved): array
    {
        return [
            'questionnaire' => [
                'id' => $questionnaire->id,
                'version' => $questionnaire->version,
                'title' => $questionnaire->title,
                'purpose' => $questionnaire->purpose,
                'scoring_method' => $questionnaire->scoringMethod(),
                'section_weighting' => $questionnaire->usesEqualSectionWeights() ? Questionnaire::WEIGHTING_EQUAL : Questionnaire::WEIGHTING_CUSTOM,
                'result_scale' => $questionnaire->resultScale(),
            ],
            'captured_at' => now()->toIso8601String(),
            'sections' => $sections->map(fn ($section) => [
                'id' => (int) $section->id,
                'title' => $section->title,
                'category_weight' => round($weights[(int) $section->id] ?? (float) $section->category_weight, 4),
            ])->values()->all(),
            'questions' => collect($resolved)->map(function (array $pair) {
                /** @var StressQuestion $question */
                $question = $pair['question'];

                return [
                    'id' => $question->id,
                    'text' => $question->question_text,
                    'type' => $question->question_type,
                    'answer_mode' => $question->answerMode(),
                    'max_selections' => $question->allowsMultipleAnswers() ? $question->selectionLimit() : 1,
                    'scoring_method' => $question->scoringMethod(),
                    'section_id' => $question->pivot->questionnaire_section_id
                        ? (int) $question->pivot->questionnaire_section_id
                        : null,
                    'is_required' => (bool) $question->pivot->is_required,
                    'min_score' => $pair['min'],
                    'max_score' => $pair['max'],
                    'wellbeing_weight' => (float) $question->wellbeing_weight,
                    'is_reverse_scored' => (bool) $question->is_reverse_scored,
                    'stress_relevant' => (bool) $question->stress_relevant,
                    'stress_direction' => $question->stress_direction,
                    'stress_weight' => (float) $question->stress_weight,
                    'options' => $question->options->map(fn ($option) => [
                        'id' => $option->id,
                        'label' => $option->label,
                        'score' => $option->score,
                    ])->values()->all(),
                ];
            })->values()->all(),
            'score_bands' => $questionnaire->scoreBands()
                ->orderBy('scope')->orderBy('position')
                ->get(['scope', 'code', 'label', 'description', 'harmony_message', 'min_score', 'max_score', 'is_active'])
                ->map(fn ($band) => $band->only(['scope', 'code', 'label', 'description', 'harmony_message', 'min_score', 'max_score', 'is_active']))
                ->all(),
        ];
    }
}
