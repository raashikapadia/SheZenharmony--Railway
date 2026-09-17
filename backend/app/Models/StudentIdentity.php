<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class StudentIdentity extends Model
{
    /**
     * The SheZen ID a student sees and quotes: "SZ" plus five random
     * characters, e.g. SZ7K42P. The alphabet leaves out 0/O and 1/I/L so the
     * ID reads the same however it is written down. Uniqueness is enforced by
     * the database, and the code never changes once the row exists.
     */
    public const CODE_PREFIX = 'SZ';

    public const CODE_LENGTH = 5;

    public const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = ['user_id', 'pseudonymous_uuid', 'shezen_code'];

    public function displayId(): string
    {
        return $this->shezen_code;
    }

    protected static function booted(): void
    {
        static::creating(function (StudentIdentity $identity): void {
            $identity->pseudonymous_uuid ??= (string) Str::uuid();
            $identity->shezen_code ??= self::generateShezenCode();
        });
    }

    /**
     * A random code no existing student holds. The pool is ~33 million, so a
     * clash is rare; the unique index catches the race the check cannot.
     */
    public static function generateShezenCode(): string
    {
        for ($attempt = 0; $attempt < 25; $attempt++) {
            $code = self::CODE_PREFIX;
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }

            if (! static::query()->where('shezen_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not allocate a unique SheZen ID.');
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

    /** Whether the student has agreed to the consent wording currently in force. */
    public function hasCurrentConsent(): bool
    {
        return $this->consents()
            ->where('policy_version', StudentConsent::CURRENT_POLICY_VERSION)
            ->exists();
    }

    /** Records agreement to the current wording; a repeat call is a no-op. */
    public function recordCurrentConsent(): StudentConsent
    {
        return $this->consents()->firstOrCreate(
            ['policy_version' => StudentConsent::CURRENT_POLICY_VERSION],
            ['accepted_at' => now()],
        );
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StressAssessment::class);
    }

    public function latestAssessment(): HasOne
    {
        return $this->hasOne(StressAssessment::class)
            ->where('assessment_status', 'completed')
            ->latestOfMany('completed_at');
    }

    public function interventionUsages(): HasMany
    {
        return $this->hasMany(InterventionUsage::class);
    }

    public function favouriteGuidance(): BelongsToMany
    {
        return $this->belongsToMany(
            PersonalGuidance::class,
            'personal_guidance_favourites',
            'student_identity_id',
            'personal_guidance_id',
        )->withTimestamps();
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(ProgressEntry::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function gratitudeEntries(): HasMany
    {
        return $this->hasMany(GratitudeEntry::class);
    }

    public function diaries(): HasMany
    {
        return $this->hasMany(Diary::class);
    }

    /**
     * The single PIN this student uses for every diary they lock.
     */
    public function diaryLock(): HasOne
    {
        return $this->hasOne(DiaryLock::class);
    }
}
