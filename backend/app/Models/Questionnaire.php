<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Questionnaire extends Model
{
    protected $fillable = [
        'title', 'description', 'period', 'type', 'version', 'status', 'is_active',
        'created_by_user_id', 'published_at',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'is_active' => 'boolean', 'published_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(StressQuestion::class, 'questionnaire_questions')
            ->withPivot(['position', 'is_required'])->withTimestamps();
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
