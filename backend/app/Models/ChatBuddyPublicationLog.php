<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ChatBuddyPublicationLog extends Model { protected $guarded = ['id']; public function release(): BelongsTo { return $this->belongsTo(ChatBuddyRelease::class, 'chat_buddy_release_id'); } public function user(): BelongsTo { return $this->belongsTo(User::class); } }
