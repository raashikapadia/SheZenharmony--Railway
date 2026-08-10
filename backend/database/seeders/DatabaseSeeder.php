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
                'content_type' => 'breathing',
                'stress_level' => null,
                'external_url' => null,
                'is_active' => true,
            ]
        );

        Intervention::query()->updateOrCreate(
            ['title' => 'Gratitude reflection'],
            [
                'description' => 'Development example: note three things you appreciate today.',
                'content_type' => 'journaling',
                'stress_level' => null,
                'external_url' => null,
                'is_active' => true,
            ]
        );
    }
}
