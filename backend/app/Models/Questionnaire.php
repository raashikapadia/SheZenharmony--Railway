<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Questionnaire extends Model
{
    /** How long a deleted questionnaire stays recoverable before it is purged. */
    public const TRASH_RETENTION_DAYS = 7;

    protected $fillable = [
        'title', 'description', 'period', 'result_scale_min', 'result_scale_max', 'type', 'version', 'status', 'is_active',
        'created_by_user_id', 'published_at', 'trashed_at', 'purge_after',
    ];

    /**
     * The client's fixed result scale as [min, max], or null when results
     * are reported as raw points totals.
     *
     * @return array{0: int, 1: int}|null
     */
    public function resultScale(): ?array
    {
        if ($this->result_scale_min === null || $this->result_scale_max === null) {
            return null;
        }

        return [(int) $this->result_scale_min, (int) $this->result_scale_max];
    }

    /** Published, but not open to students until its go-live time. */
    public function isScheduled(): bool
    {
        return $this->is_active && $this->published_at !== null && $this->published_at->isFuture();
    }

    /** The one-word state an admin sees: Live, Scheduled, Draft or Archived. */
    public function publishState(): string
    {
        if ($this->is_active) {
            return $this->isScheduled() ? 'Scheduled' : 'Live';
        }

        return ucfirst((string) $this->status);
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'result_scale_min' => 'integer',
            'result_scale_max' => 'integer',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'trashed_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    public function scopeNotInTrash(Builder $query): Builder
    {
        return $query->whereNull('trashed_at');
    }

    public function scopeInTrash(Builder $query): Builder
    {
        return $query->whereNotNull('trashed_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(StressQuestion::class, 'questionnaire_questions')
            ->withPivot(['position', 'is_required', 'questionnaire_section_id'])->withTimestamps();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(QuestionnaireSection::class)->orderBy('position')->orderBy('id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(QuestionnaireAuditLog::class)->latest();
    }

    public function scoreBands(): HasMany
    {
        return $this->hasMany(StressScoreBand::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StressAssessment::class);
    }
}
