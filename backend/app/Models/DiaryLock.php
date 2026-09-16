<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The one PIN a student uses for every diary they lock.
 *
 * Only the salted hash of the PIN is stored, exactly as the device holds it —
 * the PIN itself is never sent to the server. It lives here rather than on each
 * diary so that locking a second diary reuses the PIN the student already
 * chose instead of asking for another one.
 */
class DiaryLock extends Model
{
    protected $fillable = ['salt', 'hash', 'client_updated_at'];

    /**
     * Never echoed back in a response. The device already has it, and sending
     * it only widens where it could leak from.
     */
    protected $hidden = ['salt', 'hash'];

    protected function casts(): array
    {
        return ['client_updated_at' => 'datetime'];
    }

    public function studentIdentity(): BelongsTo
    {
        return $this->belongsTo(StudentIdentity::class);
    }
}
