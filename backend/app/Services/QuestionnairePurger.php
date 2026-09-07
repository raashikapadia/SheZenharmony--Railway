<?php

namespace App\Services;

use App\Models\Questionnaire;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Permanently removes a trashed questionnaire and everything it owns
 * (sections, question memberships, score bands, audit trail). Refuses when
 * any assessment was completed against it — that history is kept and the
 * questionnaire stays archived instead.
 *
 * Used both by the admin "delete permanently" action and the scheduled
 * purge of questionnaires whose recovery window has elapsed.
 */
class QuestionnairePurger
{
    /**
     * @throws RuntimeException when the questionnaire has assessment history
     */
    public function purge(Questionnaire $questionnaire): void
    {
        if ($questionnaire->assessments()->exists()) {
            throw new RuntimeException('Questionnaire has assessment history and cannot be purged.');
        }

        DB::transaction(function () use ($questionnaire): void {
            $questionnaire->scoreBands()->delete();
            $questionnaire->questions()->detach();
            $questionnaire->sections()->delete();
            $questionnaire->auditLogs()->delete();
            $questionnaire->delete();
        });
    }

    /**
     * Trashed questionnaires whose recovery window has elapsed. Any that
     * turn out to have assessment history are rejected by purge() and should
     * be restored by the caller rather than deleted.
     *
     * @return Collection<int, Questionnaire>
     */
    public function dueForPurge(): Collection
    {
        return Questionnaire::query()
            ->inTrash()
            ->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->get();
    }
}
