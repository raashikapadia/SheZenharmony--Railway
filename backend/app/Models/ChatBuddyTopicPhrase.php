<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ChatBuddyTopicPhrase extends Model { protected $guarded = ['id']; protected static function booted(): void { static::saved(function (ChatBuddyTopicPhrase $phrase): void { $phrase->topic()->where('is_safety', true)->first()?->release()->update(['safety_content_approved' => false, 'safety_content_hash' => null]); }); } public function topic(): BelongsTo { return $this->belongsTo(ChatBuddyTopic::class, 'chat_buddy_topic_id'); } }
