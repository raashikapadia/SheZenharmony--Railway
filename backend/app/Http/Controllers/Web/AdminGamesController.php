<?php

namespace App\Http\Controllers\Web;

class AdminGamesController extends AdminInterventionController
{
    protected function configuration(): array
    {
        return [
            'route' => 'admin.positive-engagement.games',
            'title' => 'Games',
            'singular' => 'Game',
            'heading' => 'Games',
            'description' => 'Manage games and other interactive positive engagement activities shown in the student application.',
            'empty' => 'No games configured.',
            'studentSection' => 'Positive Engagement',
            'contentTypes' => [
                'positive_engagement' => 'Game',
            ],
        ];
    }
}