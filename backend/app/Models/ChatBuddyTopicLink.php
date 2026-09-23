<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ChatBuddyTopicLink extends Model { protected $guarded = ['id']; protected static function booted(): void { static::saved(function (ChatBuddyTopicLink $link): void { $link->topic()->where('is_safety', true)->first()?->release()->update(['safety_content_approved' => false, 'safety_content_hash' => null]); }); } public function topic(): BelongsTo { return $this->belongsTo(ChatBuddyTopic::class, 'chat_buddy_topic_id'); } }
