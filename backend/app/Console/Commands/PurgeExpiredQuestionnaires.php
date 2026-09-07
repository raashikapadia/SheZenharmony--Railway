<?php

namespace App\Console\Commands;

use App\Models\QuestionnaireAuditLog;
use App\Services\QuestionnairePurger;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Permanently removes questionnaires that were trashed more than
 * Questionnaire::TRASH_RETENTION_DAYS ago and were never restored. Intended
 * to run daily from the scheduler; safe to run by hand at any time.
 */
class PurgeExpiredQuestionnaires extends Command
{
    protected $signature = 'shezen:purge-questionnaires {--dry-run : List what would be purged without deleting anything}';

    protected $description = 'Permanently delete questionnaires whose trash recovery window has elapsed';

    public function handle(QuestionnairePurger $purger): int
    {
        $due = $purger->dueForPurge();

        if ($due->isEmpty()) {
            $this->info('Nothing to purge.');

            return self::SUCCESS;
        }

        foreach ($due as $questionnaire) {
            $label = "\"{$questionnaire->title}\" (v{$questionnaire->version})";

            if ($this->option('dry-run')) {
                $this->line("Would purge {$label} — trashed {$questionnaire->trashed_at?->toDateString()}");

                continue;
            }

            try {
                $purger->purge($questionnaire);
                QuestionnaireAuditLog::query()->create([
                    'user_id' => null,
                    'action' => 'questionnaire.purged',
                    'description' => "Scheduled purge removed {$label}.",
                    'created_at' => now(),
                ]);
                $this->info("Purged {$label}.");
            } catch (RuntimeException $e) {
                // Assessment history appeared after trashing — keep it.
                $questionnaire->update(['trashed_at' => null, 'purge_after' => null]);
                $this->warn("Skipped {$label}: {$e->getMessage()} (restored).");
            }
        }

        return self::SUCCESS;
    }
}
