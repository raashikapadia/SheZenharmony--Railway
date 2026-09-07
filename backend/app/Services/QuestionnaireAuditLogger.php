<?php

namespace App\Services;

use App\Models\QuestionnaireAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes one append-only accountability row per administrator change to a
 * questionnaire's structure or scoring configuration (spec section 38).
 * Best-effort: a logging failure must never block the admin action, so
 * callers may wrap calls, but in practice a simple insert is used.
 */
class QuestionnaireAuditLogger
{
    public function log(
        ?int $questionnaireId,
        string $action,
        string $description,
        ?Model $entity = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        QuestionnaireAuditLog::query()->create([
            'questionnaire_id' => $questionnaireId,
            'user_id' => Auth::id(),
            'action' => $action,
            'entity' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
        ]);
    }
}
