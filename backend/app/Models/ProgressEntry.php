<?php

namespace App\Models;

use App\Models\Concerns\RequiresExactlyOneOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressEntry extends Model
{
    use RequiresExactlyOneOwner;

    protected $guarded = ['id'];

    protected function casts(): array { return ['metric_value' => 'decimal:2', 'recorded_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function anonymousSession(): BelongsTo { return $this->belongsTo(AnonymousSession::class); }
    public function assessment(): BelongsTo { return $this->belongsTo(StressAssessment::class, 'stress_assessment_id'); }
    public function interventionUsage(): BelongsTo { return $this->belongsTo(InterventionUsage::class); }
}
