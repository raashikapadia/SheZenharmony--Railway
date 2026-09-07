<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionRecommendation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'is_active' => 'boolean'];
    }

    public function scoreBand(): BelongsTo
    {
        return $this->belongsTo(StressScoreBand::class, 'stress_score_band_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSection::class, 'questionnaire_section_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }
}
