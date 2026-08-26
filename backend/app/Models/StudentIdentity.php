<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class StudentIdentity extends Model
{
    protected $fillable = ['user_id', 'pseudonymous_uuid'];

    public function displayId(): string
    {
        return 'SZ-'.strtoupper(str_replace('-', '', $this->pseudonymous_uuid));
    }

    protected static function booted(): void
    {
        static::creating(function (StudentIdentity $identity): void {
            $identity->pseudonymous_uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(StudentConsent::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StressAssessment::class);
    }

    public function interventionUsages(): HasMany
    {
        return $this->hasMany(InterventionUsage::class);
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(ProgressEntry::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }
}
