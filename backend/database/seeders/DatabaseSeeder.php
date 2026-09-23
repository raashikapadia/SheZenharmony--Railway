<?php

namespace Database\Seeders;

use App\Models\Intervention;
use App\Models\QuestionOption;
use App\Models\StressQuestion;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // DEVELOPMENT ONLY.
        // Replace these with the client-approved framework/questions/scoring before real data collection.
        $question = StressQuestion::query()->updateOrCreate(
            ['question_text' => 'DEMO: How stressed do you feel right now?'],
            [
                'dimension' => 'demo',
                'question_type' => 'scale',
                'position' => 1,
                'is_active' => true,
                'is_sensitive' => false,
            ]
        );

        foreach ([
            ['label' => 'Very low', 'value' => '1', 'score' => 1, 'position' => 1],
            ['label' => 'Low', 'value' => '2', 'score' => 2, 'position' => 2],
            ['label' => 'Moderate', 'value' => '3', 'score' => 3, 'position' => 3],
            ['label' => 'High', 'value' => '4', 'score' => 4, 'position' => 4],
            ['label' => 'Very high', 'value' => '5', 'score' => 5, 'position' => 5],
        ] as $option) {
            QuestionOption::query()->updateOrCreate(
                [
                    'stress_question_id' => $question->id,
                    'value' => $option['value'],
                ],
                $option + ['stress_question_id' => $question->id]
            );
        }

        Intervention::query()->updateOrCreate(
            ['title' => 'Box breathing'],
            [
                'description' => 'Development example: a short guided breathing activity.',
                'slug' => 'box-breathing',
                'content_type' => 'breathing',
                'instructions' => 'Breathe in gently for 4 counts, hold for 4, breathe out for 4, and hold for 4. Repeat for four comfortable rounds without forcing your breath.',
                'stress_level' => null,
                'external_url' => null,
                'is_active' => true,
            ]
        );

        Intervention::query()->updateOrCreate(
            ['title' => 'Gratitude reflection'],
            [
                'description' => 'Development example: note three things you appreciate today.',
                'slug' => 'gratitude-reflection',
                'content_type' => 'journaling',
                'instructions' => 'Pause and name three things you appreciate today. They can be small: a kind message, a quiet moment, or something you managed well.',
                'stress_level' => null,
                'external_url' => null,
                'is_active' => true,
            ]
        );

        // A runnable starter questionnaire + the canonical score bands, so a
        // freshly migrated database can serve an assessment immediately.
        // No-ops once an admin has published a real stress questionnaire.
        $this->call(StressFrameworkSeeder::class);

        // The supplied 74-question / 8-category wellbeing instrument. Publishes
        // and activates itself (archiving the starter). No-ops once seeded.
        $this->call(WellbeingQuestionnaireSeeder::class);

        // A small curated set of Home Page encouragement. No-ops once any
        // Personal Guidance row exists.
        $this->call(PersonalGuidanceSeeder::class);

        // Starter helplines for the student Resource tab. No-ops once any
        // helpline row exists. Numbers are unverified placeholders.
        $this->call(HelplineResourceSeeder::class);
        $this->call(ChatBuddySampleSeeder::class);
    }
}
