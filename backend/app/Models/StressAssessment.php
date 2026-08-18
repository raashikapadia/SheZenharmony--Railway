<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StressAssessment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (StressAssessment $assessment): void {
            $assessment->public_uuid ??= (string) Str::uuid();
            $assessment->started_at ??= now();
        });
    }

    protected $fillable = [
        'user_id',
        'anonymous_session_id',
        'anonymous_session_fk',
        'public_uuid',
        'stress_score_band_id',
        'assessment_type',
        'assessment_status',
        'total_score',
        'stress_level',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(StressResponse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function anonymousSession(): BelongsTo
    {
        return $this->belongsTo(AnonymousSession::class, 'anonymous_session_fk');
    }

    public function scoreBand(): BelongsTo
    {
        return $this->belongsTo(StressScoreBand::class, 'stress_score_band_id');
    }
}
