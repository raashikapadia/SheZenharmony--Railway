<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = [
        'stress_question_id',
        'label',
        'value',
        'score',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(StressQuestion::class, 'stress_question_id');
    }
}
