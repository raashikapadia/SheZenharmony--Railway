<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatBuddyTopic extends Model
{
    protected $guarded = ['id'];
    protected static function booted(): void
    {
        static::saved(function (ChatBuddyTopic $topic): void {
            if ($topic->is_safety) $topic->release()->update(['safety_content_approved' => false, 'safety_content_hash' => null]);
        });
    }
    protected function casts(): array { return ['priority' => 'integer', 'is_safety' => 'boolean']; }
    public function release(): BelongsTo { return $this->belongsTo(ChatBuddyRelease::class, 'chat_buddy_release_id'); }
    public function phrases(): HasMany { return $this->hasMany(ChatBuddyTopicPhrase::class)->orderBy('position'); }
    public function followUpPrompts(): HasMany { return $this->hasMany(ChatBuddyFollowUpPrompt::class)->orderBy('position'); }
    public function links(): HasMany { return $this->hasMany(ChatBuddyTopicLink::class)->orderBy('position'); }
}
