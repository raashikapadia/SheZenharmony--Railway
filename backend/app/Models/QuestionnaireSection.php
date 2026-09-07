<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A wellbeing category within one questionnaire version: an ordered,
 * weighted grouping of questions. Replaces the free-text
 * `stress_questions.dimension` grouping for questionnaires that opt into
 * the dynamic wellbeing engine.
 */
class QuestionnaireSection extends Model
{
    protected $fillable = [
        'questionnaire_id',
        'title',
        'description',
        'position',
        'category_weight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'category_weight' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    /**
     * Questions placed in this section, via the shared questionnaire_questions
     * pivot. Order and required-ness live on the pivot.
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(StressQuestion::class, 'questionnaire_questions')
            ->withPivot(['position', 'is_required', 'questionnaire_id'])
            ->withTimestamps();
    }
}
