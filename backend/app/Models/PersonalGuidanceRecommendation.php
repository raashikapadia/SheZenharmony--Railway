<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rule linking a piece of guidance to an assessment outcome. Mirrors
 * {@see InterventionRecommendation} so both content types match the same way.
 */
class PersonalGuidanceRecommendation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'is_active' => 'boolean'];
    }

    public function guidance(): BelongsTo
    {
        return $this->belongsTo(PersonalGuidance::class, 'personal_guidance_id');
    }

    public function scoreBand(): BelongsTo
    {
        return $this->belongsTo(StressScoreBand::class, 'stress_score_band_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSection::class, 'questionnaire_section_id');
    }
}
