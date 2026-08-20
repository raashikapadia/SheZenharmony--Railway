<?php

namespace App\Models;

use App\Models\Concerns\RequiresExactlyOneOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterventionUsage extends Model
{
    use RequiresExactlyOneOwner;

    protected $fillable = [
        'stress_assessment_id',
        'intervention_id',
        'user_id',
        'anonymous_session_id',
        'anonymous_session_fk',
        'usage_status',
        'mood_before',
        'mood_after',
        'started_at',
        'completed_at',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'mood_before' => 'integer',
            'mood_after' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StressAssessment::class, 'stress_assessment_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function anonymousSession(): BelongsTo
    {
        return $this->belongsTo(AnonymousSession::class, 'anonymous_session_fk');
    }

    protected function anonymousOwnerColumns(): array
    {
        return ['anonymous_session_fk', 'anonymous_session_id'];
    }
}
