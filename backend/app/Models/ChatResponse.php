<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A prewritten reply for one intent, with the buttons offered after it. */
class ChatResponse extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'priority' => 'integer'];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(ChatIntent::class, 'chat_intent_id');
    }

    public function quickReplies(): HasMany
    {
        return $this->hasMany(ChatQuickReply::class)->orderBy('position');
    }
}
