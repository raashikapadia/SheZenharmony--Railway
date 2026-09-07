<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only accountability record for administrator changes to
 * questionnaire structure and scoring configuration. Written by
 * QuestionnaireAuditLogger; never updated or deleted in normal flow.
 */
class QuestionnaireAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'questionnaire_id',
        'user_id',
        'action',
        'entity',
        'entity_id',
        'description',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaire::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
