<?php

namespace App\Services;

use App\Models\CategoryResult;
use App\Models\StressAssessment;
use App\Models\StressResponse;
use App\Models\StressScoreBand;
use Illuminate\Support\Collection;

/**
 * The single source of aggregate assessment analytics. Both
 * "Questionnaire Management → Analytics" (wellbeing lens) and
 * "Stress Level Assessment → Analytics" (stress lens) render tabs off this
 * one dataset — there is no separate stress analytics computation.
 *
 * Everything is counts / averages only; no student is identifiable. Groups
 * smaller than SUPPRESS are hidden from demographic breakdowns.
 */
class AssessmentAnalytics
{
    private const SUPPRESS = 5;

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            ...$this->summaryData(),
            'wellbeing_bands' => $this->wellbeingBandDistribution(),
            'stress_bands' => $this->stressBandDistribution(),
            'categories' => $this->categoryAverages(),
            'questions' => $this->questionAverages(),
            'by_questionnaire' => $this->perQuestionnaire(),
            'demographics' => $this->demographics(),
            'stress_vs_wellbeing' => $this->stressVsWellbeing(),
        ];
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        return [
            ...$this->summaryData(),
            'stress_bands' => $this->stressBandDistribution(),
        ];
    }

    /** @return array<string, mixed> */
    public function forTab(string $tab): array
    {
        return match ($tab) {
            'categories' => ['categories' => $this->categoryAverages()],
            'questions' => ['questions' => $this->questionAverages()],
            'demographics' => ['demographics' => $this->demographics()],
            'vs' => ['stress_vs_wellbeing' => $this->stressVsWellbeing()],
            'trends', 'factors', 'protective' => [],
            'overall' => [
                ...$this->summaryData(),
                'wellbeing_bands' => $this->wellbeingBandDistribution(),
                'stress_bands' => $this->stressBandDistribution(),
                'by_questionnaire' => $this->perQuestionnaire(),
            ],
            default => $this->forTab('overall'),
        };
    }

    /** @return array<string, mixed> */
    private function summaryData(): array
    {
        $completed = fn () => StressAssessment::query()->where('assessment_status', 'completed');

        $totalStarted = StressAssessment::query()->count();
        $totalCompleted = $completed()->count();

        return [
            'totals' => [
                'started' => $totalStarted,
                'completed' => $totalCompleted,
                'last_30_days' => $completed()->where('completed_at', '>=', now()->subDays(30))->count(),
                'students' => $completed()->distinct()->count('student_identity_id'),
                'completion_rate' => $totalStarted > 0 ? round($totalCompleted / $totalStarted * 100) : null,
            ],
            'averages' => [
                'overall_percentage' => $this->round($completed()->whereNotNull('overall_percentage')->avg('overall_percentage')),
                'stress_score' => $this->round($completed()->whereNotNull('stress_score')->avg('stress_score')),
            ],
        ];
    }

    /** @return Collection<int, array{label: string, total: int}> */
    private function wellbeingBandDistribution()
    {
        $dist = StressAssessment::query()->where('assessment_status', 'completed')
            ->selectRaw('COALESCE(wellbeing_result_band_id, stress_score_band_id) AS band_id, COUNT(*) AS total')
            ->groupBy('band_id')->pluck('total', 'band_id');

        $labels = StressScoreBand::query()->whereIn('id', $dist->keys()->filter())->pluck('label', 'id');

        return $dist->map(fn ($total, $id) => ['label' => $labels[$id] ?? 'Unbanded', 'total' => (int) $total])
            ->values()->sortByDesc('total')->values();
    }

    /** @return Collection<int, array{label: string, total: int}> */
    private function stressBandDistribution()
    {
        $dist = StressAssessment::query()->where('assessment_status', 'completed')
            ->whereNotNull('stress_result_band_id')
            ->selectRaw('stress_result_band_id, COUNT(*) AS total')
            ->groupBy('stress_result_band_id')
            ->pluck('total', 'stress_result_band_id');

        $labels = StressScoreBand::query()->whereIn('id', $dist->keys())->pluck('label', 'id');

        return $dist->map(fn ($total, $id) => [
            'label' => $labels[$id] ?? 'Unbanded',
            'total' => (int) $total,
        ])->values();
    }

    private function categoryAverages()
    {
        return CategoryResult::query()
            ->selectRaw('section_title_snapshot, COUNT(*) AS responses, AVG(percentage) AS avg_percentage')
            ->groupBy('section_title_snapshot')->orderBy('section_title_snapshot')->get()
            ->map(fn ($r) => [
                'title' => $r->section_title_snapshot,
                'responses' => (int) $r->responses,
                'avg_percentage' => $this->round($r->avg_percentage),
            ]);
    }

    private function questionAverages()
    {
        return StressResponse::query()
            ->whereNotNull('question_text_snapshot')
            ->selectRaw('question_text_snapshot, COUNT(*) AS answers, AVG(score) AS avg_score')
            ->groupBy('question_text_snapshot')
            ->orderByDesc('avg_score')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'question' => $r->question_text_snapshot,
                'answers' => (int) $r->answers,
                'avg_score' => $this->round($r->avg_score, 2),
            ]);
    }

    private function perQuestionnaire()
    {
        return StressAssessment::query()->where('assessment_status', 'completed')
            ->whereNotNull('questionnaire_id')
            ->selectRaw('questionnaire_id, COUNT(*) AS total')
            ->groupBy('questionnaire_id')
            ->with('questionnaire:id,title,version')
            ->get()
            ->map(fn ($r) => [
                'title' => $r->questionnaire?->title ?? '—',
                'version' => $r->questionnaire?->version,
                'total' => (int) $r->total,
            ]);
    }

    /** @return array<string, Collection> */
    private function demographics(): array
    {
        return [
            'year_of_study' => $this->demographicBreakdown('year_of_study'),
            'gender' => $this->demographicBreakdown('gender'),
            'country' => $this->demographicBreakdown('country'),
        ];
    }

    private function demographicBreakdown(string $column)
    {
        return StressAssessment::query()->where('assessment_status', 'completed')
            ->join('user_profiles', 'user_profiles.student_identity_id', '=', 'stress_assessments.student_identity_id')
            ->whereNotNull("user_profiles.{$column}")
            ->selectRaw("user_profiles.{$column} AS bucket, COUNT(*) AS total, AVG(stress_assessments.overall_percentage) AS avg_wellbeing, AVG(stress_assessments.stress_score) AS avg_stress")
            ->groupBy("user_profiles.{$column}")
            ->orderByDesc('total')
            ->get()
            ->filter(fn ($r) => (int) $r->total >= self::SUPPRESS)
            ->map(fn ($r) => [
                'bucket' => $r->bucket,
                'total' => (int) $r->total,
                'avg_wellbeing' => $this->round($r->avg_wellbeing),
                'avg_stress' => $this->round($r->avg_stress),
            ])
            ->values();
    }

    /**
     * A light Stress-vs-Wellbeing view: average stress score within each
     * wellbeing result band. Enough to see the expected inverse relationship
     * without a full correlation model.
     */
    private function stressVsWellbeing()
    {
        return StressAssessment::query()->where('assessment_status', 'completed')
            ->whereNotNull('wellbeing_result_band_id')->whereNotNull('stress_score')
            ->join('stress_score_bands', 'stress_score_bands.id', '=', 'stress_assessments.wellbeing_result_band_id')
            ->selectRaw('stress_score_bands.label AS band, COUNT(*) AS total, AVG(stress_assessments.stress_score) AS avg_stress, AVG(stress_assessments.overall_percentage) AS avg_wellbeing')
            ->groupBy('stress_score_bands.label')
            ->orderByDesc('avg_wellbeing')
            ->get()
            ->map(fn ($r) => [
                'band' => $r->band,
                'total' => (int) $r->total,
                'avg_stress' => $this->round($r->avg_stress),
                'avg_wellbeing' => $this->round($r->avg_wellbeing),
            ]);
    }

    private function round(mixed $value, int $precision = 1): ?float
    {
        return $value === null ? null : round((float) $value, $precision);
    }
}
