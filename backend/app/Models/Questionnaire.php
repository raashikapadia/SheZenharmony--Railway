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

    /**
     * The mandatory baseline a student completes straight after signing up.
     * Exactly one family carries this purpose, and it is never listed among
     * the questionnaires a student chooses to sit.
     */
    public const PURPOSE_REGISTRATION = 'registration';

    /**
     * An independently created questionnaire a student may choose to sit.
     * Many of these can be live at once — one live version per family.
     */
    public const PURPOSE_LIBRARY = 'library';

    /** @return array<int, string> */
    public static function purposes(): array
    {
        return [self::PURPOSE_REGISTRATION, self::PURPOSE_LIBRARY];
    }

    /**
     * Overall result = the points total of every answer (reverse scoring and
     * question weights applied), normalised onto the result scale. Section
     * weights only shape the per-section breakdown. The historical default.
     */
    public const SCORING_POINTS_TOTAL = 'points_total';

    /**
     * Overall result = Σ over sections of (section raw ÷ section maximum ×
     * section weight), so each section contributes exactly its weight when
     * every answer is at maximum. The maximum total is the sum of weights.
     */
    public const SCORING_WEIGHTED_SECTIONS = 'weighted_sections';

    /** @return array<int, string> */
    public static function scoringMethods(): array
    {
        return [self::SCORING_POINTS_TOTAL, self::SCORING_WEIGHTED_SECTIONS];
    }

    /** Each active section uses the weight stored on it. */
    public const WEIGHTING_CUSTOM = 'custom';

    /** Every active section counts the same; the engine derives the weights. */
    public const WEIGHTING_EQUAL = 'equal';

    /** @return array<int, string> */
    public static function sectionWeightings(): array
    {
        return [self::WEIGHTING_CUSTOM, self::WEIGHTING_EQUAL];
    }

    protected $fillable = [
        'title', 'description', 'period', 'result_scale_min', 'result_scale_max', 'type', 'purpose',
        'scoring_method', 'section_weighting', 'estimated_minutes',
        'version', 'status', 'is_active', 'created_by_user_id', 'published_at', 'trashed_at', 'purge_after',
    ];

    public function scoringMethod(): string
    {
        return $this->scoring_method ?: self::SCORING_POINTS_TOTAL;
    }

    public function usesWeightedSections(): bool
    {
        return $this->scoringMethod() === self::SCORING_WEIGHTED_SECTIONS;
    }

    public function usesEqualSectionWeights(): bool
    {
        return ($this->section_weighting ?: self::WEIGHTING_CUSTOM) === self::WEIGHTING_EQUAL;
    }

    /** Plain-language name of the scoring method, for admin screens. */
    public function scoringMethodLabel(): string
    {
        return match ($this->scoringMethod()) {
            self::SCORING_WEIGHTED_SECTIONS => 'Weighted sections',
            default => 'Points total',
        };
    }

    public function isRegistration(): bool
    {
        return $this->purpose === self::PURPOSE_REGISTRATION;
    }

    /**
     * Open to students right now: published, active and past its go-live
     * time. The one availability rule the list endpoint, the detail endpoint
     * and submission all share, so a student is never offered a
     * questionnaire they cannot answer.
     */
    public function isAvailable(): bool
    {
        return $this->status === 'published' && $this->is_active && ! $this->isScheduled();
    }

    /**
     * The version-family key for a new questionnaire, derived from its title
     * so each one versions independently of every other. Falls back to a
     * hashed name when the title has no usable characters, and takes a
     * numeric suffix when another family already holds the slug.
     */
    public static function deriveType(string $title, ?int $ignoreId = null): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($title)), '_');
        $base = $base === ''
            ? 'questionnaire_'.substr(md5($title.microtime()), 0, 8)
            : substr($base, 0, 40);

        $candidate = $base;
        $suffix = 2;
        while (self::query()
            ->where('type', $candidate)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $candidate = substr($base, 0, 39 - strlen((string) $suffix)).'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

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
            'estimated_minutes' => 'integer',
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

    public function scopeRegistration(Builder $query): Builder
    {
        return $query->where('purpose', self::PURPOSE_REGISTRATION);
    }

    public function scopeLibrary(Builder $query): Builder
    {
        return $query->where('purpose', self::PURPOSE_LIBRARY);
    }

    /** Published, active and past its go-live time — see {@see isAvailable()}. */
    public function scopeAvailableToStudents(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where('is_active', true)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
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
