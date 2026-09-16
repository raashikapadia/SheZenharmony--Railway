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
            'description' => 'Manage motivational messages and other positive activities shown in the student application. For a motivational message, put the whole message in the title and keep it short — it is shown to students exactly as written.',
            'empty' => 'No positive engagement content configured.',
            'studentSection' => 'Positive Engagement',
            'contentTypes' => [
                'motivation' => 'Motivational message (short — title is the message)',
            ],
        ];
    }
}
