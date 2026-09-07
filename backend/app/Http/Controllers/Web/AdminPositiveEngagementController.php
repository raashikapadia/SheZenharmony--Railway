<?php

namespace App\Http\Controllers\Web;

class AdminPositiveEngagementController extends AdminInterventionController
{
    protected function configuration(): array
    {
        return [
            'route' => 'admin.positive-engagement',
            'title' => 'Positive Engagement',
            'singular' => 'Positive engagement item',
            'heading' => 'Positive activities',
            'description' => 'Manage quizzes, motivational prompts, and other positive activities shown in the student application.',
            'empty' => 'No positive engagement content configured.',
            'studentSection' => 'Positive Engagement',
            'contentTypes' => [
                'quiz' => 'Quiz / mini-game',
                'motivation' => 'Motivational content',
                'positive_engagement' => 'Other positive activity',
            ],
        ];
    }
}
