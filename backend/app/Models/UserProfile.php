<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    /** The one option that asks the student to spell out their situation. */
    public const YEAR_OF_STUDY_OTHER = 'Other';

    public const YEAR_OF_STUDY_OPTIONS = [
        'Year 1',
        'Year 2',
        'Year 3',
        'Year 4',
        'Postgraduate',
        self::YEAR_OF_STUDY_OTHER,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'has_children' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The free-text detail only means anything alongside "Other", so a
        // change of selection drops it rather than leaving a stale answer.
        static::saving(function (UserProfile $profile): void {
            if ($profile->year_of_study !== self::YEAR_OF_STUDY_OTHER) {
                $profile->year_of_study_detail = null;
            }
        });
    }

    /**
     * Always derived from date_of_birth — never stored or settable, so it
     * can never drift out of sync with the DOB a student or admin saves.
     */
    protected function age(): Attribute
    {
        return Attribute::make(get: fn () => $this->date_of_birth?->age);
    }

    /** "Other" spelled out with what the student typed, e.g. "Other (Part-time diploma)". */
    public function yearOfStudyDescription(): ?string
    {
        if ($this->year_of_study === self::YEAR_OF_STUDY_OTHER && $this->year_of_study_detail) {
            return self::YEAR_OF_STUDY_OTHER.' ('.$this->year_of_study_detail.')';
        }

        return $this->year_of_study;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studentIdentity(): BelongsTo
    {
        return $this->belongsTo(StudentIdentity::class);
    }
}
