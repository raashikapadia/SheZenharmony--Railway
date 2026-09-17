<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentConsent extends Model
{
    /**
     * The survey participation consent students must agree to before using
     * the app. Bump this whenever the consent wording changes: every student
     * is then asked again on next open, because an acceptance recorded for
     * older wording does not cover the new one. Accepting is the only state
     * recorded — declining deletes the account outright.
     */
    public const CURRENT_POLICY_VERSION = 'shezen-survey-consent-v2';

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
