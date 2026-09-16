<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One written entry inside a {@see Diary}.
 *
 * Both the title and the body are encrypted at rest, so the row is meaningless
 * to anyone reading the table or a database backup directly.
 */
class DiaryPage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'title',
        'body',
        'client_created_at',
        'client_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'encrypted',
            'body' => 'encrypted',
            'client_created_at' => 'datetime',
            'client_updated_at' => 'datetime',
        ];
    }

    public function diary(): BelongsTo
    {
        return $this->belongsTo(Diary::class);
    }
}
