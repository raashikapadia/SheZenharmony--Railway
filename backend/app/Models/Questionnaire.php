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
        'title', 'description', 'period', 'type', 'version', 'status', 'is_active',
        'created_by_user_id', 'published_at', 'trashed_at', 'purge_after',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
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
