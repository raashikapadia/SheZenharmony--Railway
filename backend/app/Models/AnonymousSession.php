<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AnonymousSession extends Model
{
    protected $fillable = ['public_uuid', 'started_at', 'last_seen_at', 'expires_at', 'ended_at'];

    protected static function booted(): void
    {
        static::creating(function (AnonymousSession $session): void {
            $session->public_uuid ??= (string) Str::uuid();
            $session->started_at ??= now();
        });
    }

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_seen_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StressAssessment::class, 'anonymous_session_fk');
    }

    public function interventionUsages(): HasMany
    {
        return $this->hasMany(InterventionUsage::class, 'anonymous_session_fk');
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(ProgressEntry::class);
    }
}
