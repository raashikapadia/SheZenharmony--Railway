<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatBuddyRelease extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['suggested_topics' => 'array', 'safety_content_approved' => 'boolean', 'published_at' => 'datetime'];
    }
    protected static function booted(): void { static::saving(function (ChatBuddyRelease $release): void { if ($release->isDirty(['safety_message'])) { $release->safety_content_approved = false; $release->safety_content_hash = null; } }); }

    public function scopeDraft(Builder $query): Builder { return $query->where('current_key', 'draft'); }
    public function scopePublished(Builder $query): Builder { return $query->where('current_key', 'published'); }
    public function topics(): HasMany { return $this->hasMany(ChatBuddyTopic::class); }
    public function publicationLogs(): HasMany { return $this->hasMany(ChatBuddyPublicationLog::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function publishedBy(): BelongsTo { return $this->belongsTo(User::class, 'published_by_user_id'); }
}
