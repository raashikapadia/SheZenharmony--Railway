<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tappable button under a Shezen reply. It either moves the conversation to
 * another intent, or sends the student to an existing area of the app.
 */
class ChatQuickReply extends Model
{
    /** Sections a quick reply may link to. Keep in step with the Flutter app. */
    public const LINK_TARGETS = [
        'personal_guidance' => 'Personal Guidance',
        'wellbeing_activities' => 'Wellbeing Activities',
        'positive_engagement' => 'Positive Engagement',
        'games' => 'Games and quizzes',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(ChatResponse::class);
    }

    public function nextIntent(): BelongsTo
    {
        return $this->belongsTo(ChatIntent::class, 'next_chat_intent_id');
    }
}
