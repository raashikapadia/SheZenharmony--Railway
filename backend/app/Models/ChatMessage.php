<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_flagged' => 'boolean'];
    }

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function senderStudentIdentity(): BelongsTo
    {
        return $this->belongsTo(StudentIdentity::class, 'sender_student_identity_id');
    }

    public function crisisReport(): HasOne
    {
        return $this->hasOne(CrisisReport::class);
    }
}
