<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ChatBuddyFollowUpPrompt extends Model { protected $guarded = ['id']; public function topic(): BelongsTo { return $this->belongsTo(ChatBuddyTopic::class, 'chat_buddy_topic_id'); } }
