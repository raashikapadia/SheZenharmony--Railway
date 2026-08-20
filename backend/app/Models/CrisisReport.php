<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrisisReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['reported_at' => 'datetime', 'resolved_at' => 'datetime']; }
    public function chatSession(): BelongsTo { return $this->belongsTo(ChatSession::class); }
    public function chatMessage(): BelongsTo { return $this->belongsTo(ChatMessage::class); }
    public function handler(): BelongsTo { return $this->belongsTo(User::class, 'handled_by_user_id'); }
}
