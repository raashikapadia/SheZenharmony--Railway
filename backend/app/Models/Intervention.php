<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Intervention extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'content_type',
        'stress_level',
        'external_url',
        'app_screen',
        'instructions',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(InterventionUsage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(InterventionRecommendation::class);
    }

    /**
     * Stress bands this intervention is recommended for. No linked bands means
     * the intervention applies to every level ("all levels").
     */
    public function scoreBands(): BelongsToMany
    {
        return $this->belongsToMany(StressScoreBand::class, 'intervention_recommendations')
            ->withPivot(['priority', 'is_active'])
            ->withTimestamps();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ContentCategory::class, 'intervention_content_categories')->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'intervention_tags')->withTimestamps();
    }
}
