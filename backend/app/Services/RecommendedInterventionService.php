<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\StressScoreBand;
use Illuminate\Support\Collection;

/**
 * Resolves the published interventions an admin has recommended for a given
 * stress band. An intervention matches when it is explicitly linked to the
 * band through an active `intervention_recommendations` row, or when it has
 * no active recommendation rows at all — the "all levels" case, which keeps
 * the historical `stress_level = null` meaning.
 */
class RecommendedInterventionService
{
    /** @return Collection<int, Intervention> */
    public function forBand(StressScoreBand $band, int $limit = 8): Collection
    {
        return Intervention::query()
            ->where('is_active', true)
            // Games are interactive screens students open from Positive
            // Engagement, and the games admin deliberately offers no band
            // targeting. Without this they would match the "all levels" case
            // below and be recommended after every assessment.
            ->where('content_type', '!=', 'game')
            ->where(function ($query) use ($band): void {
                $query
                    ->whereHas('recommendations', fn ($recommendation) => $recommendation
                        ->where('is_active', true)
                        ->where('stress_score_band_id', $band->id))
                    ->orWhereDoesntHave('recommendations', fn ($recommendation) => $recommendation
                        ->where('is_active', true));
            })
            ->with(['recommendations' => fn ($recommendation) => $recommendation
                ->where('is_active', true)
                ->where('stress_score_band_id', $band->id)])
            ->orderBy('title')
            ->get()
            ->sortBy(fn (Intervention $intervention) => $intervention->recommendations->min('priority') ?? PHP_INT_MAX)
            ->take($limit)
            ->values();
    }

    /** @return array<string, mixed> */
    public function payload(Intervention $intervention): array
    {
        return [
            'title' => $intervention->title,
            'description' => $intervention->description,
            'content_type' => $intervention->content_type,
            'instructions' => $intervention->instructions,
            'external_url' => $intervention->external_url,
            // The app screen this support opens, when the admin chose one.
            'app_screen' => $intervention->app_screen,
        ];
    }
}
