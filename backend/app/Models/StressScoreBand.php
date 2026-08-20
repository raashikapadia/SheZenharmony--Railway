<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StressScoreBand extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['min_score' => 'integer', 'max_score' => 'integer', 'position' => 'integer', 'is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StressAssessment::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(InterventionRecommendation::class);
    }
}
