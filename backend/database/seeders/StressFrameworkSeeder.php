<?php

namespace Database\Seeders;

use App\Models\Intervention;
use App\Models\Questionnaire;
use App\Models\StressQuestion;
use App\Models\StressScoreBand;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a runnable starter stress framework so a freshly migrated database
 * has an active questionnaire and the canonical score bands
 * (0-29 Low / 30-59 Moderate / 60-79 High / 80-100 Very High).
 *
 * DEVELOPMENT / BOOTSTRAP ONLY. The placeholder questions below are clearly
 * labelled "STARTER" and are meant to be replaced with the client-approved
 * assessment framework through the admin panel. This seeder is idempotent
 * and never touches an existing published questionnaire.
 */
class StressFrameworkSeeder extends Seeder
{
    public const QUESTIONNAIRE_TITLE = 'Stress Check-In (starter)';

    /** Canonical bands. Editable afterwards via the admin panel. */
    public const BANDS = [
        ['code' => 'low', 'label' => 'Low', 'min_score' => 0, 'max_score' => 29, 'position' => 1],
        ['code' => 'moderate', 'label' => 'Moderate', 'min_score' => 30, 'max_score' => 59, 'position' => 2],
        ['code' => 'high', 'label' => 'High', 'min_score' => 60, 'max_score' => 79, 'position' => 3],
        ['code' => 'very_high', 'label' => 'Very High', 'min_score' => 80, 'max_score' => 100, 'position' => 4],
    ];

    private const OPTIONS = [
        ['label' => 'Not at all', 'value' => 'none', 'score' => 0],
        ['label' => 'A little', 'value' => 'mild', 'score' => 3],
        ['label' => 'Quite a bit', 'value' => 'moderate', 'score' => 7],
        ['label' => 'A great deal', 'value' => 'severe', 'score' => 10],
    ];

    private const QUESTIONS = [
        'I have felt overwhelmed by everything I need to do.',
        'I have found it hard to switch off or relax.',
        'Stress has made it difficult to concentrate.',
        'I have felt tense, restless, or on edge.',
        'I have felt that things were outside my control.',
        'I have slept poorly because of worry or racing thoughts.',
        'I have felt easily irritated or frustrated.',
        'I have felt low on energy or motivation.',
        'I have worried about my studies or responsibilities.',
        'I have felt unable to cope with the demands on me.',
    ];

    public function run(): void
    {
        // Never override an admin-configured framework.
        if (Questionnaire::query()
            ->where('type', 'stress')
            ->where('status', 'published')
            ->where('is_active', true)
            ->exists()
        ) {
            return;
        }

        $adminId = User::query()->where('role', User::ROLE_ADMIN)->value('id');

        DB::transaction(function () use ($adminId): void {
            $questionnaire = Questionnaire::query()->firstOrCreate(
                ['title' => self::QUESTIONNAIRE_TITLE],
                [
                    'description' => 'Starter self-check. Replace with the approved assessment framework via the admin panel.',
                    'period' => 'Starter',
                    'type' => 'stress',
                    'version' => (int) Questionnaire::query()->where('type', 'stress')->max('version') + 1,
                    'status' => 'published',
                    'is_active' => true,
                    'created_by_user_id' => $adminId,
                    'published_at' => now(),
                ],
            );

            $questionnaire->update([
                'status' => 'published',
                'is_active' => true,
                'published_at' => $questionnaire->published_at ?? now(),
            ]);

            $memberships = [];
            foreach (self::QUESTIONS as $index => $text) {
                $position = $index + 1;
                $question = StressQuestion::query()->updateOrCreate(
                    ['code' => 'starter-stress-'.$position],
                    [
                        'question_text' => 'STARTER — '.$text,
                        'dimension' => 'starter',
                        'help_text' => 'Starter question; replace with the approved framework.',
                        'question_type' => 'scale',
                        'position' => $position,
                        'is_required' => true,
                        'is_active' => true,
                        'is_sensitive' => false,
                        'created_by_user_id' => $adminId,
                    ],
                );

                foreach (self::OPTIONS as $optionIndex => $option) {
                    $question->options()->updateOrCreate(
                        ['value' => $option['value']],
                        $option + ['position' => $optionIndex + 1, 'is_active' => true],
                    );
                }

                $memberships[$question->id] = ['position' => $position, 'is_required' => true];
            }

            $questionnaire->questions()->sync($memberships);

            $bandsByCode = [];
            foreach (self::BANDS as $band) {
                $bandsByCode[$band['code']] = $questionnaire->scoreBands()->updateOrCreate(
                    ['code' => $band['code']],
                    $band + ['is_active' => true, 'created_by_user_id' => $adminId],
                );
            }

            $this->seedRecommendations($bandsByCode);
        });
    }

    /**
     * "Box breathing" (seeded by DatabaseSeeder) stays all-levels — no rows.
     * A counselling resource is targeted at the higher bands so the
     * recommendation flow is demonstrable out of the box.
     *
     * @param  array<string, StressScoreBand>  $bandsByCode
     */
    private function seedRecommendations(array $bandsByCode): void
    {
        $counselling = Intervention::query()->updateOrCreate(
            ['title' => 'Talk to someone'],
            [
                'slug' => 'talk-to-someone',
                'description' => 'Confidential support from the university counselling service.',
                'content_type' => 'resource',
                'instructions' => 'You do not need to be in crisis to reach out. Counselling staff can help you talk things through and plan next steps.',
                'external_url' => null,
                'is_active' => true,
            ],
        );

        foreach (['high', 'very_high'] as $code) {
            if (isset($bandsByCode[$code])) {
                $counselling->recommendations()->updateOrCreate(
                    ['stress_score_band_id' => $bandsByCode[$code]->id],
                    ['priority' => 0, 'is_active' => true],
                );
            }
        }
    }
}
