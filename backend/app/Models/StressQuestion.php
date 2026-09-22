<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class StressQuestion extends Model
{
    public const STRESS_DIRECTION_MORE = 'higher_more_stress';

    public const STRESS_DIRECTION_LESS = 'higher_less_stress';

    /** The student picks exactly one answer option. */
    public const ANSWER_SINGLE = 'single';

    /** The student may tick several options (capped by `max_selections`). */
    public const ANSWER_MULTIPLE = 'multiple';

    /** @return array<int, string> */
    public static function answerModes(): array
    {
        return [self::ANSWER_SINGLE, self::ANSWER_MULTIPLE];
    }

    /**
     * Points = the chosen option's points; for a multi-select question, the
     * sum of every ticked option's points.
     */
    public const SCORING_DIRECT = 'direct';

    /** Multi-select: one point per ticked option, whatever the options say. */
    public const SCORING_COUNT_SELECTED = 'count_selected';

    /** Multi-select: the highest points among the ticked options. */
    public const SCORING_MAX_SELECTED = 'max_selected';

    /** @return array<int, string> */
    public static function scoringMethods(): array
    {
        return [self::SCORING_DIRECT, self::SCORING_COUNT_SELECTED, self::SCORING_MAX_SELECTED];
    }

    protected $fillable = [
        'question_text',
        'code',
        'dimension',
        'help_text',
        'question_type',
        'answer_mode',
        'max_selections',
        'scoring_method',
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
            'max_selections' => 'integer',
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

    public function answerMode(): string
    {
        return $this->answer_mode ?: self::ANSWER_SINGLE;
    }

    public function allowsMultipleAnswers(): bool
    {
        return $this->answerMode() === self::ANSWER_MULTIPLE;
    }

    /** The per-question scoring method; single-answer questions are always direct. */
    public function scoringMethod(): string
    {
        return $this->allowsMultipleAnswers()
            ? ($this->scoring_method ?: self::SCORING_DIRECT)
            : self::SCORING_DIRECT;
    }

    /**
     * How many options a student may tick: `max_selections` when set, else
     * every active option. Always 1 for a single-answer question.
     */
    public function selectionLimit(): int
    {
        if (! $this->allowsMultipleAnswers()) {
            return 1;
        }
        $optionCount = max(1, $this->activeOptionScores()->count());
        $limit = $this->max_selections !== null ? (int) $this->max_selections : $optionCount;

        return max(1, min($limit, $optionCount));
    }

    /**
     * The lowest scored value this question can produce. Falls back to what
     * the answer mode and scoring method allow when `min_score` is not set
     * explicitly, so reverse scoring stays correct even as options change.
     */
    public function resolvedMinScore(): ?int
    {
        if ($this->min_score !== null) {
            return (int) $this->min_score;
        }

        return $this->optionScoreRange()[0] ?? null;
    }

    /**
     * The highest scored value this question can produce. Falls back to what
     * the answer mode and scoring method allow when `max_score` is not set.
     */
    public function resolvedMaxScore(): ?int
    {
        if ($this->max_score !== null) {
            return (int) $this->max_score;
        }

        return $this->optionScoreRange()[1] ?? null;
    }

    /**
     * The lowest and highest points the active options can yield under this
     * question's answer mode and scoring method, ignoring any explicit
     * min/max override. Null when no option carries points.
     *
     * @return array{0: int, 1: int}|null
     */
    public function optionScoreRange(): ?array
    {
        $scores = $this->activeOptionScores();
        if ($scores->isEmpty()) {
            return null;
        }
        if (! $this->allowsMultipleAnswers()) {
            return [$scores->min(), $scores->max()];
        }

        $limit = $this->selectionLimit();

        // Ticking nothing scores nothing, so a multi-select floor is zero
        // unless an option carries negative points; the ceiling is the best
        // the allowed number of ticks can reach.
        return match ($this->scoringMethod()) {
            self::SCORING_COUNT_SELECTED => [0, $limit],
            self::SCORING_MAX_SELECTED => [min(0, $scores->min()), $scores->max()],
            default => [
                (int) $scores->filter(fn (int $s) => $s < 0)->sort()->take($limit)->sum(),
                (int) $scores->filter(fn (int $s) => $s > 0)->sortDesc()->take($limit)->sum(),
            ],
        };
    }

    /** @return Collection<int, int> */
    private function activeOptionScores(): Collection
    {
        $scores = $this->relationLoaded('options')
            ? $this->options->where('is_active', true)->pluck('score')
            : $this->options()->where('is_active', true)->pluck('score');

        return $scores->filter(fn ($s) => $s !== null)->map(fn ($s) => (int) $s)->values();
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
