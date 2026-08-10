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
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'position' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(StressQuestion::class, 'stress_question_id');
    }
}
