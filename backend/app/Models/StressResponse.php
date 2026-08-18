<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StressResponse extends Model
{
    protected $fillable = [
        'stress_assessment_id',
        'stress_question_id',
        'question_option_id',
        'numeric_value',
        'answer_text',
        'score',
        'question_text_snapshot',
        'option_text_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'numeric_value' => 'decimal:2',
            'score' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StressAssessment::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(StressQuestion::class, 'stress_question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(QuestionOption::class, 'question_option_id');
    }
}
