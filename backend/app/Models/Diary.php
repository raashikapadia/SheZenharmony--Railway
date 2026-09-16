<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A student's diary, stored so it outlives the device it was written on.
 *
 * The title is encrypted at rest and there is deliberately no admin route,
 * controller or Blade view that reads this table. A diary reaches only the
 * student who wrote it, through the pseudonymous identity boundary.
 */
class Diary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'title',
        'cover_index',
        'is_locked',
        'client_created_at',
        'client_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'encrypted',
            'cover_index' => 'integer',
            // Whether this diary asks for the PIN. Which PIN is not recorded
            // here: a student has one, held in `diary_locks`.
            'is_locked' => 'boolean',
            'client_created_at' => 'datetime',
            'client_updated_at' => 'datetime',
        ];
    }

    public function studentIdentity(): BelongsTo
    {
        return $this->belongsTo(StudentIdentity::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(DiaryPage::class);
    }
}
