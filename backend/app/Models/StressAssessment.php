<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StressAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'anonymous_session_id',
        'total_score',
        'stress_level',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(StressResponse::class);
    }
}
