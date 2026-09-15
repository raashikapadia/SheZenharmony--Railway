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
 * Two modes, chosen automatically:
 *
 *  - **Flat** (no active sections): historical behaviour — the total is the
 *    sum of the chosen option scores and one `scope = 'overall'` band is
 *    matched against it. Untouched by the wellbeing engine.
 *  - **Weighted wellbeing** (active sections present): each section is
 *    normalised and weighted into an overall weighted score, an optional
 *    0-100 stress sub-score is derived from stress-relevant questions, and a
 *    per-question config snapshot is returned so the result can be
 *    recomputed later without reading live configuration.
 *
 * Nothing here invents clinical thresholds — reverse scoring, stress
 * relevance/direction/weight and every result range come from stored admin
 * configuration.
 */
class AssessmentScoringService
{
    /**
     * @param  array<int, int>  $answers  question id => chosen option id
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

        // Resolve every answer to its option, validating scorability once.
        $resolved = [];
        $flatTotal = 0;
        foreach ($answers as $questionId => $optionId) {
            $question = $questions->get($questionId);
            $option = $question?->options->firstWhere('id', $optionId);

            if (! $option || $option->score === null) {
                throw ValidationException::withMessages([
                    'answers' => 'An answer option is invalid, inactive, or not scorable.',
                ]);
            }

            $resolved[$questionId] = ['question' => $question, 'option' => $option];
            $flatTotal += (int) $option->score;
        }

        $activeSections = $questionnaire->sections;

        if ($activeSections->isEmpty()) {
            // Same rule as the sectioned path: the raw total is normalised
            // onto the client's scale when one is configured.
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

        return $this->scoreWeighted($questionnaire, $activeSections, $resolved, $flatTotal);
    }

    /**
     * @param  Collection<int, QuestionnaireSection>  $sections
     * @param  array<int, array{question: StressQuestion, option: QuestionOption}>  $resolved
     */
    private function scoreWeighted(Questionnaire $questionnaire, Collection $sections, array $resolved, int $flatTotal): array
    {
        $categories = [];
        $overallRaw = 0.0;
        $overallWeighted = 0.0;
        $overallMaxWeighted = 0.0;
        $responses = [];

        foreach ($sections as $section) {
            $overallMaxWeighted += (float) $section->category_weight;

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

                $optionScore = (int) $pair['option']->score;
                $qMin = $question->resolvedMinScore() ?? $optionScore;
                $qMax = $question->resolvedMaxScore() ?? $optionScore;
                $weight = (float) $question->wellbeing_weight;

                $scoredValue = $question->is_reverse_scored
                    ? ($qMax + $qMin - $optionScore)
                    : $optionScore;

                $raw += $scoredValue * $weight;
                $minPossible += $qMin * $weight;
                $maxPossible += $qMax * $weight;
                $answeredInSection++;

                $responses[$questionId] = [
                    'question' => $question,
                    'option' => $pair['option'],
                    'scored_value' => $scoredValue,
                ];
            }

            // Category score as a share of its maximum possible points, then
            // scaled by the category weight — a 10-question, all-max section
            // (raw 50 / max 50) contributes its full weight of 5, "all
            // neutral" (raw 30 / max 50) contributes 3.
            $percentage = $maxPossible > 0
                ? $this->clampPercent($raw / $maxPossible * 100)
                : 0.0;
            $weightedScore = ($percentage / 100) * (float) $section->category_weight;

            $overallRaw += $raw;
            $overallWeighted += $weightedScore;

            $categories[] = [
                'section_id' => (int) $section->id,
                'title' => $section->title,
                'answered_questions' => $answeredInSection,
                'raw_score' => round($raw, 2),
                'min_possible_score' => round($minPossible, 2),
                'max_possible_score' => round($maxPossible, 2),
                'percentage' => round($percentage, 2),
                'category_weight' => (float) $section->category_weight,
                'weighted_score' => round($weightedScore, 2),
            ];
        }

        // Any question left in $resolved but not matched to a section is a
        // configuration error activation should have blocked; fold its raw
        // value into responses so the transcript stays complete.
        foreach ($resolved as $questionId => $pair) {
            if (! isset($responses[$questionId])) {
                $responses[$questionId] = [
                    'question' => $pair['question'],
                    'option' => $pair['option'],
                    'scored_value' => (int) $pair['option']->score,
                ];
            }
        }

        $overallPercentage = $overallMaxWeighted > 0
            ? $this->clampPercent($overallWeighted / $overallMaxWeighted * 100)
            : 0.0;

        // The raw points total (reverse scoring and per-question weight
        // applied) is normalised into the client's fixed result scale, so
        // the scale and the ranges written on it hold still however many
        // questions there are. Without a configured scale the raw total is
        // the result. Rounded to a whole point before matching so a value
        // can never land in the gap between two adjacent ranges.
        [$rawMin, $rawMax] = self::possibleTotalRange($questionnaire->questions);
        $overallTotal = self::normalise($overallRaw, $rawMin, $rawMax, $questionnaire->resultScale());
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
                'categories' => $categories,
                'overall' => [
                    'raw_score' => round($overallRaw, 2),
                    'raw_min' => $rawMin,
                    'raw_max' => $rawMax,
                    'result_score' => $overallTotal,
                    'result_scale' => $questionnaire->resultScale(),
                    'weighted_score' => round($overallWeighted, 2),
                    'max_weighted_score' => round($overallMaxWeighted, 2),
                    'percentage' => round($overallPercentage, 2),
                    'band' => ['code' => $wellbeingBand->code, 'label' => $wellbeingBand->label],
                ],
                'stress' => $stress === null ? null : [
                    'score' => round($stress['score'], 2),
                    'percentage' => round($stress['score'], 2),
                    'band' => $stressBand ? ['code' => $stressBand->code, 'label' => $stressBand->label] : null,
                ],
                'flat_total' => $flatTotal,
            ],
            'config_snapshot' => $this->buildConfigSnapshot($questionnaire, $sections, $resolved),
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
     * @param  array<int, array{question: StressQuestion, option: QuestionOption}>  $resolved
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

            $optionScore = (int) $pair['option']->score;
            $qMin = $question->resolvedMinScore() ?? $optionScore;
            $qMax = $question->resolvedMaxScore() ?? $optionScore;

            $fraction = $qMax > $qMin ? ($optionScore - $qMin) / ($qMax - $qMin) : 0.0;
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

    /**
     * @param  Collection<int, StressQuestion>  $questions
     * @param  array<int, int>  $answers
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
     * @param  array<int, int>  $answers
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
            $label = $scope === StressScoreBand::SCOPE_STRESS ? 'stress' : 'wellbeing';
            throw ValidationException::withMessages([
                'score_bands' => "No active {$label} result range is configured for score ".round($score, 2).'.',
            ]);
        }

        return $band;
    }

    /**
     * @param  array<int, array{question: StressQuestion, option: QuestionOption}>  $resolved
     * @return array<int, array<string, mixed>>
     */
    private function flatResponses(array $resolved): array
    {
        $out = [];
        foreach ($resolved as $questionId => $pair) {
            $out[$questionId] = [
                'question' => $pair['question'],
                'option' => $pair['option'],
                'scored_value' => (int) $pair['option']->score,
            ];
        }

        return $out;
    }

    /**
     * The lowest and highest overall total a sectioned questionnaire can
     * produce, from its questions' answer points and weights — exactly the
     * span the overall result ranges have to cover. Optional questions can
     * be skipped, so they only widen the span, never narrow it.
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
     * scores, no weights or reverse scoring — matching how it is totalled.
     *
     * @param  Collection<int, StressQuestion>  $questions  with options loaded and the questionnaire pivot
     * @return array{0: int, 1: int}
     */
    public static function flatTotalRange(Collection $questions): array
    {
        $min = 0;
        $max = 0;
        foreach ($questions as $question) {
            $scores = $question->options->where('is_active', true)->pluck('score')->filter(fn ($s) => $s !== null);
            if ($scores->isEmpty()) {
                continue;
            }
            $required = (bool) ($question->pivot->is_required ?? true);
            $min += $required ? (int) $scores->min() : min(0, (int) $scores->min());
            $max += max(0, (int) $scores->max());
        }

        return [$min, $max];
    }

    /**
     * The span the overall result ranges must cover: the client's result
     * scale when one is configured, otherwise the raw points span.
     *
     * @param  Collection<int, StressQuestion>  $questions  with options loaded and the questionnaire pivot
     * @return array{0: int, 1: int}
     */
    public static function resultSpan(Questionnaire $questionnaire, Collection $questions): array
    {
        return $questionnaire->resultScale() ?? self::possibleTotalRange($questions);
    }

    /**
     * Map a raw total onto the client's result scale, e.g. 111 of 0–148 on
     * a 0–40 scale → 30. Returns the rounded raw total when there is no
     * scale, and the scale's minimum when the raw span is empty.
     *
     * @param  array{0: int, 1: int}|null  $scale
     */
    public static function normalise(float $raw, int $rawMin, int $rawMax, ?array $scale): int
    {
        if ($scale === null) {
            return (int) round($raw);
        }
        [$scaleMin, $scaleMax] = $scale;
        if ($rawMax <= $rawMin) {
            return $scaleMin;
        }
        $fraction = max(0.0, min(1.0, ($raw - $rawMin) / ($rawMax - $rawMin)));

        return (int) round($fraction * ($scaleMax - $scaleMin) + $scaleMin);
    }

    private function clampPercent(float $value): float
    {
        return max(0.0, min(100.0, $value));
    }

    /**
     * Immutable copy of every scoring rule this result depended on.
     *
     * @param  Collection<int, QuestionnaireSection>  $sections
     * @param  array<int, array{question: StressQuestion, option: QuestionOption}>  $resolved
     * @return array<string, mixed>
     */
    private function buildConfigSnapshot(Questionnaire $questionnaire, Collection $sections, array $resolved): array
    {
        return [
            'questionnaire' => [
                'id' => $questionnaire->id,
                'version' => $questionnaire->version,
                'title' => $questionnaire->title,
            ],
            'captured_at' => now()->toIso8601String(),
            'sections' => $sections->map(fn ($section) => [
                'id' => (int) $section->id,
                'title' => $section->title,
                'category_weight' => (float) $section->category_weight,
            ])->values()->all(),
            'questions' => collect($resolved)->map(function (array $pair) {
                /** @var StressQuestion $question */
                $question = $pair['question'];

                return [
                    'id' => $question->id,
                    'text' => $question->question_text,
                    'type' => $question->question_type,
                    'section_id' => $question->pivot->questionnaire_section_id
                        ? (int) $question->pivot->questionnaire_section_id
                        : null,
                    'is_required' => (bool) $question->pivot->is_required,
                    'min_score' => $question->resolvedMinScore(),
                    'max_score' => $question->resolvedMaxScore(),
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
                ->get(['scope', 'code', 'label', 'min_score', 'max_score', 'is_active'])
                ->map(fn ($band) => $band->only(['scope', 'code', 'label', 'min_score', 'max_score', 'is_active']))
                ->all(),
        ];
    }
}
