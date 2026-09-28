<?php

namespace App\Services;

use App\Models\PersonalGuidance;
use App\Models\StressAssessment;
use App\Models\StudentIdentity;
use Illuminate\Support\Collection;

/**
 * Chooses the guidance that best fits a student's most recent assessment.
 *
 * The rule chain is deliberately the same shape as
 * {@see RecommendedInterventionService}: a piece of guidance matches when an
 * active rule links it to the student's stress band or to one of the
 * questionnaire sections they scored highest on. Guidance carrying no active
 * rules is treated as "applies to everyone", so content written before this
 * feature existed keeps showing up.
 *
 * Nothing here interprets or labels the student — it only orders existing,
 * admin-authored content.
 */
class RecommendedGuidanceService
{
    /**
     * How many sections count as "what this student needs support with".
     * Sections are ranked by percentage, highest first.
     */
    private const SECTION_LIMIT = 3;

    /** The student's latest completed assessment, or null when they have none. */
    public function latestAssessment(StudentIdentity $identity): ?StressAssessment
    {
        return StressAssessment::query()
            ->where('student_identity_id', $identity->id)
            ->whereNotNull('completed_at')
            ->with('categoryResults')
            ->latest('completed_at')
            ->first();
    }

    /**
     * Guidance matched to the assessment, most relevant first. Falls back to
     * general published guidance so the student never sees an empty toolkit.
     *
     * Matches tips and quotes alike — a quote can carry the same band/section
     * rules a tip can, so "Recommended for You" is never tip-only.
     *
     * @return Collection<int, PersonalGuidance>
     */
    public function forAssessment(?StressAssessment $assessment, int $limit = 4): Collection
    {
        $bandId = $assessment?->stress_score_band_id;
        $sectionIds = $this->focusSectionIds($assessment);
        $types = [PersonalGuidance::TYPE_GUIDANCE, PersonalGuidance::TYPE_QUOTE];

        $matched = PersonalGuidance::query()
            ->visible()
            ->whereIn('type', $types)
            ->whereHas('recommendations', function ($rule) use ($bandId, $sectionIds): void {
                $rule->where('is_active', true)
                    ->where(function ($match) use ($bandId, $sectionIds): void {
                        if ($bandId !== null) {
                            $match->orWhere('stress_score_band_id', $bandId);
                        }

                        if ($sectionIds !== []) {
                            $match->orWhereIn('questionnaire_section_id', $sectionIds);
                        }
                    });
            })
            ->with($this->eagerLoads($bandId, $sectionIds))
            ->get()
            ->sortBy(fn (PersonalGuidance $guidance) => $guidance->recommendations->min('priority') ?? PHP_INT_MAX)
            ->values();

        if ($matched->count() >= $limit) {
            return $matched->take($limit);
        }

        // Top up with guidance that applies to everyone (no active rules).
        $general = PersonalGuidance::query()
            ->visible()
            ->whereIn('type', $types)
            ->whereDoesntHave('recommendations', fn ($rule) => $rule->where('is_active', true))
            ->whereNotIn('id', $matched->pluck('id')->all())
            ->with('relatedIntervention')
            ->latest('id')
            ->take($limit - $matched->count())
            ->get();

        return $matched->concat($general)->values();
    }

    /**
     * The sections this student scored highest on — the areas where support is
     * most likely to help. Returns an empty list when there is no assessment.
     *
     * @return array<int, int>
     */
    public function focusSectionIds(?StressAssessment $assessment): array
    {
        if ($assessment === null) {
            return [];
        }

        return $assessment->categoryResults
            ->sortByDesc('percentage')
            ->take(self::SECTION_LIMIT)
            ->pluck('questionnaire_section_id')
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function payload(PersonalGuidance $guidance): array
    {
        return [
            'id' => $guidance->id,
            'type' => $guidance->type,
            'title' => $guidance->title,
            'summary' => $guidance->summary,
            'when_it_helps' => $guidance->when_it_helps,
            'steps' => $guidance->stepList(),
            'resource_url' => $guidance->resource_url,
            'duration_minutes' => $guidance->duration_minutes,
            'content' => $guidance->content,
            'author' => $guidance->attribution(),
            'category' => $guidance->categoryName(),
            'related_activity' => $guidance->relatedIntervention === null ? null : [
                'title' => $guidance->relatedIntervention->title,
                'content_type' => $guidance->relatedIntervention->content_type,
                'instructions' => $guidance->relatedIntervention->instructions,
            ],
        ];
    }

    /**
     * Eager loads the rules that actually matched, so `priority` ordering above
     * reflects this student rather than every rule on the row.
     *
     * @param  array<int, int>  $sectionIds
     * @return array<string, callable|string>
     */
    private function eagerLoads(?int $bandId, array $sectionIds): array
    {
        return [
            'relatedIntervention',
            'recommendations' => function ($rule) use ($bandId, $sectionIds): void {
                $rule->where('is_active', true)
                    ->where(function ($match) use ($bandId, $sectionIds): void {
                        if ($bandId !== null) {
                            $match->orWhere('stress_score_band_id', $bandId);
                        }

                        if ($sectionIds !== []) {
                            $match->orWhereIn('questionnaire_section_id', $sectionIds);
                        }
                    });
            },
        ];
    }
}
