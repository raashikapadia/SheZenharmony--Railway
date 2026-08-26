<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentConsent extends Model
{
    protected $fillable = ['policy_version', 'accepted_at'];

    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime'];
    }

    public function studentIdentity(): BelongsTo
    {
        return $this->belongsTo(StudentIdentity::class);
    }
}
