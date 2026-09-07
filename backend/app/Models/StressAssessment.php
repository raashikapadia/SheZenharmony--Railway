<?php

namespace App\Models;

use App\Models\Concerns\RequiresExactlyOneOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StressAssessment extends Model
{
    use RequiresExactlyOneOwner;

    protected static function booted(): void
    {
        static::creating(function (StressAssessment $assessment): void {
            $assessment->public_uuid ??= (string) Str::uuid();
            $assessment->started_at ??= now();
        });
    }

    protected $fillable = [
        'user_id',
        'student_identity_id',
        'questionnaire_id',
        'anonymous_session_id',
        'anonymous_session_fk',
        'public_uuid',
        'stress_score_band_id',
        'assessment_type',
        'assessment_status',
        'total_score',
        'stress_level',
        'overall_raw_score',
        'overall_weighted_score',
        'overall_max_weighted_score',
        'overall_percentage',
        'wellbeing_result_band_id',
        'stress_score',
        'stress_percentage',
        'stress_result_band_id',
        'config_snapshot',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'integer',
            'overall_raw_score' => 'decimal:2',
            'overall_weighted_score' => 'decimal:2',
            'overall_max_weighted_score' => 'decimal:2',
            'overall_percentage' => 'decimal:2',
            'stress_score' => 'decimal:2',
            'stress_percentage' => 'decimal:2',
            'config_snapshot' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(StressResponse::class);
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studentIdentity(): BelongsTo
    {
        return $this->belongsTo(StudentIdentity::class);
    }

    public function anonymousSession(): BelongsTo
    {
        return $this->belongsTo(AnonymousSession::class, 'anonymous_session_fk');
    }

    public function scoreBand(): BelongsTo
    {
        return $this->belongsTo(StressScoreBand::class, 'stress_score_band_id');
    }

    public function categoryResults(): HasMany
    {
        return $this->hasMany(CategoryResult::class);
    }

    public function wellbeingBand(): BelongsTo
    {
        return $this->belongsTo(StressScoreBand::class, 'wellbeing_result_band_id');
    }

    public function stressBand(): BelongsTo
    {
        return $this->belongsTo(StressScoreBand::class, 'stress_result_band_id');
    }

    protected function anonymousOwnerColumns(): array
    {
        return ['anonymous_session_fk', 'anonymous_session_id'];
    }
}
