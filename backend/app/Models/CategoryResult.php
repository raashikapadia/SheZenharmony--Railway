<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One wellbeing category's outcome for a single completed assessment.
 * `section_title_snapshot` and a nullable section FK keep the row readable
 * after the section is archived or a new questionnaire version is published.
 */
class CategoryResult extends Model
{
    protected $fillable = [
        'stress_assessment_id',
        'questionnaire_section_id',
        'section_title_snapshot',
        'raw_score',
        'min_possible_score',
        'max_possible_score',
        'percentage',
        'category_weight',
        'weighted_score',
    ];

    protected function casts(): array
    {
        return [
            'raw_score' => 'decimal:2',
            'min_possible_score' => 'decimal:2',
            'max_possible_score' => 'decimal:2',
            'percentage' => 'decimal:2',
            'category_weight' => 'decimal:2',
            'weighted_score' => 'decimal:2',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StressAssessment::class, 'stress_assessment_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireSection::class, 'questionnaire_section_id');
    }
}
