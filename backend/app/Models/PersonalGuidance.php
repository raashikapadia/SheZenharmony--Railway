<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A small piece of encouragement shown on the student Home Page. Content is
 * fully admin-managed — nothing here is hard-coded in the app.
 */
class PersonalGuidance extends Model
{
    public const TYPE_AFFIRMATION = 'affirmation';

    public const TYPE_QUOTE = 'quote';

    public const TYPE_GUIDANCE = 'guidance';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_UNPUBLISHED = 'unpublished';

    public const TYPES = [self::TYPE_AFFIRMATION, self::TYPE_QUOTE, self::TYPE_GUIDANCE];

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_UNPUBLISHED];

    protected $table = 'personal_guidance';

    protected $fillable = [
        'type',
        'title',
        'summary',
        'when_it_helps',
        'steps',
        'duration_minutes',
        'related_intervention_id',
        'content',
        'author',
        'category',
        'status',
        'publish_at',
        'expires_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /** Rules matching this guidance to assessment outcomes. */
    public function recommendations(): HasMany
    {
        return $this->hasMany(PersonalGuidanceRecommendation::class, 'personal_guidance_id');
    }

    /** An optional wellbeing activity to try alongside this guidance. */
    public function relatedIntervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class, 'related_intervention_id');
    }

    /** The steps stored as one per line, ready to render as a list. */
    public function stepList(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->steps))
            ->map(fn (string $step): string => trim($step))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Published, inside its schedule window — the only rows a student may see.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(fn (Builder $q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isVisible(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && ($this->publish_at === null || $this->publish_at->lte(now()))
            && ($this->expires_at === null || $this->expires_at->gt(now()));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function favouritedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            StudentIdentity::class,
            'personal_guidance_favourites',
            'personal_guidance_id',
            'student_identity_id',
        )->withTimestamps();
    }

    /**
     * The attribution to render, or null when none should be shown. Quotes
     * always carry their author; guidance may carry an optional signature;
     * affirmations never show one. Never returns "Unknown".
     */
    public function attribution(): ?string
    {
        $author = trim((string) $this->author);

        return match ($this->type) {
            self::TYPE_QUOTE => $author !== '' ? $author : null,
            self::TYPE_GUIDANCE => $author !== '' ? $author : null,
            default => null,
        };
    }
}
