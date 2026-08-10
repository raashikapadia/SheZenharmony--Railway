<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterventionUsage extends Model
{
    protected $fillable = [
        'stress_assessment_id',
        'intervention_id',
        'anonymous_session_id',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
