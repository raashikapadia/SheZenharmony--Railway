<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'has_children' => 'boolean'];
    }

    /**
     * Always derived from date_of_birth — never stored or settable, so it
     * can never drift out of sync with the DOB a student or admin saves.
     */
    protected function age(): Attribute
    {
        return Attribute::make(get: fn () => $this->date_of_birth?->age);
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
