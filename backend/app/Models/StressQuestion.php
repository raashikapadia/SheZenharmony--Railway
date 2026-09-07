<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StressQuestion extends Model
{
    public const STRESS_DIRECTION_MORE = 'higher_more_stress';

    public const STRESS_DIRECTION_LESS = 'higher_less_stress';

    protected $fillable = [
        'question_text',
        'code',
        'dimension',
        'help_text',
        'question_type',
        'min_score',
        'max_score',
        'wellbeing_weight',
        'is_reverse_scored',
        'stress_relevant',
        'stress_direction',
        'stress_weight',
        'position',
        'is_required',
        'is_active',
        'is_sensitive',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'min_score' => 'integer',
            'max_score' => 'integer',
            'wellbeing_weight' => 'decimal:2',
            'is_reverse_scored' => 'boolean',
            'stress_relevant' => 'boolean',
            'stress_weight' => 'decimal:2',
            'position' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_sensitive' => 'boolean',
        ];
    }

    /**
     * The lowest scored value this question can produce. Falls back to the
     * smallest active option score when `min_score` is not explicitly set,
     * so reverse scoring stays correct even as options change.
     */
    public function resolvedMinScore(): ?int
    {
        if ($this->min_score !== null) {
            return (int) $this->min_score;
        }

        $scores = $this->relationLoaded('options')
            ? $this->options->where('is_active', true)->pluck('score')->filter(fn ($s) => $s !== null)
            : $this->options()->where('is_active', true)->whereNotNull('score')->pluck('score');

        return $scores->isEmpty() ? null : (int) $scores->min();
    }

    /**
     * The highest scored value this question can produce. Falls back to the
     * largest active option score when `max_score` is not explicitly set.
     */
    public function resolvedMaxScore(): ?int
    {
        if ($this->max_score !== null) {
            return (int) $this->max_score;
        }

        $scores = $this->relationLoaded('options')
            ? $this->options->where('is_active', true)->pluck('score')->filter(fn ($s) => $s !== null)
            : $this->options()->where('is_active', true)->whereNotNull('score')->pluck('score');

        return $scores->isEmpty() ? null : (int) $scores->max();
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
