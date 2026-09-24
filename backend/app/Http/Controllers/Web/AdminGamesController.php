<?php

namespace App\Http\Controllers\Web;

use App\Models\Intervention;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminGamesController extends AdminInterventionController
{
    /**
     * Games that ship as screens in the student application. They can be
     * published, hidden and renamed, but not deleted: the screen would still
     * be in the app with no row to govern it, and the play history would go
     * with the row.
     */
    public const BUILT_IN_SLUGS = [
        'breathing-challenge',
        'gratitude-jar',
        'memory-spark',
        'mindful-memory',
    ];

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
                'game' => 'Game',
            ],
        ];
    }

    /**
     * Publishes or hides a game in one click from the library table, so
     * controlling what students see does not mean opening the edit form.
     */
    public function visibility(Request $request, Intervention $intervention): RedirectResponse
    {
        $this->ensureManaged($intervention);

        $publish = $request->boolean('is_active');
        $intervention->update(['is_active' => $publish]);

        return back()->with('status', sprintf(
            '%s is now %s.',
            $intervention->title,
            $publish ? 'published to students' : 'archived and hidden from students',
        ));
    }

    public function destroy(Intervention $intervention): RedirectResponse
    {
        $this->ensureManaged($intervention);

        if (in_array($intervention->slug, self::BUILT_IN_SLUGS, true)) {
            return back()->with('status', $intervention->title.' ships with the student app and cannot be deleted. Archive it instead to remove it from the app.');
        }

        return parent::destroy($intervention);
    }
}
