<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StressQuestion extends Model
{
    protected $fillable = [
        'question_text',
        'dimension',
        'question_type',
        'position',
        'is_active',
        'is_sensitive',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }
}
