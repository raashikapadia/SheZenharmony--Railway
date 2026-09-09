<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A support contact shown in the student Resource tab — a helpline, a
 * counselling service, or a campus contact. Fully admin-managed: nothing here
 * is hard-coded in the app.
 */
class HelplineResource extends Model
{
    protected $fillable = [
        'name',
        'organisation',
        'description',
        'phone',
        'alternate_phone',
        'email',
        'website_url',
        'availability',
        'category',
        'is_emergency',
        'position',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_emergency' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** Active rows only — the ones a student may see. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The student-facing order: the admin's manual position, then
     * alphabetically so equal positions stay stable.
     *
     * `is_emergency` deliberately does not sort here. It tints and badges the
     * card instead, which leaves the admin free to decide what a student
     * should reach for first — a campus counsellor is often the better lead
     * than a national crisis line.
     */
    public function scopeInDisplayOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('position')
            ->orderBy('name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
