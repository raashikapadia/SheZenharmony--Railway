<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ChatSession extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (ChatSession $session): void {
            $session->public_uuid ??= (string) Str::uuid();
            $session->started_at ??= now();
        });
    }

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function anonymousSession(): BelongsTo { return $this->belongsTo(AnonymousSession::class); }
    public function assignedSupportUser(): BelongsTo { return $this->belongsTo(User::class, 'assigned_support_user_id'); }
    public function messages(): HasMany { return $this->hasMany(ChatMessage::class); }
}
