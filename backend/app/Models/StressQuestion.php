<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StressQuestion extends Model
{
    protected $fillable = [
        'question_text',
        'code',
        'dimension',
        'help_text',
        'question_type',
        'position',
        'is_required',
        'is_active',
        'is_sensitive',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(StressResponse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function questionnaires(): BelongsToMany
    {
        return $this->belongsToMany(Questionnaire::class, 'questionnaire_questions')
            ->withPivot(['position', 'is_required'])->withTimestamps();
    }
}
