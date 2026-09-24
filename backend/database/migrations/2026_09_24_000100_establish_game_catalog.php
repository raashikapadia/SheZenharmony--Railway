<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gives the games that ship as screens in the student application a row of
 * their own, under a dedicated "game" content type.
 *
 * Until now the four games existed only as hard-coded lists in the Blade
 * admin and in the Flutter client, so admins could not publish, hide, or
 * count them. Each game now has a stable slug the student application
 * dispatches on, which also frees admins to rename a game without breaking
 * which screen it opens.
 */
return new class extends Migration
{
    /**
     * Admins govern these rows but cannot add to the list: a new row would
     * have no screen to open. Titles and descriptions are seed values only —
     * an admin edit is never overwritten here.
     *
     * @var list<array{slug: string, title: string, description: string}>
     */
    private const GAMES = [
        [
            'slug' => 'breathing-challenge',
            'title' => 'Breathing Challenge',
            'description' => 'Follow a simple breathing rhythm and take a calm moment.',
        ],
        [
            'slug' => 'gratitude-jar',
            'title' => 'Gratitude Jar',
            'description' => 'Write down something positive and add it to your gratitude jar.',
        ],
        [
            'slug' => 'memory-spark',
            'title' => 'Memory Spark',
            'description' => 'Gently tap the sparks as they appear and practise noticing the moment.',
        ],
        [
            'slug' => 'mindful-memory',
            'title' => 'Mindful Memory',
            'description' => 'Match peaceful symbols and practise your memory mindfully.',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::GAMES as $game) {
            $existing = $this->locate($game);

            if ($existing !== null) {
                // Adopt the row rather than duplicating it. Only the columns
                // this migration owns change: the title, description and
                // published state an admin already chose are left alone.
                DB::table('interventions')->where('id', $existing->id)->update([
                    'slug' => $game['slug'],
                    'content_type' => 'game',
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('interventions')->insert([
                'title' => $game['title'],
                'slug' => $game['slug'],
                'description' => $game['description'],
                'content_type' => 'game',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // The rows stay: they may already carry play history. Only the type
        // reverts, which hands them back to the section that held them before.
        DB::table('interventions')
            ->whereIn('slug', array_column(self::GAMES, 'slug'))
            ->update(['content_type' => 'positive_engagement', 'updated_at' => now()]);
    }

    /**
     * Finds a row already standing in for this game. Environments seeded
     * before this migration carry the canonical slug; a game added by hand
     * through the admin carries a slug the earlier backfill derived from its
     * title ("breathing-challenge-7"), so fall back to matching on title.
     */
    private function locate(array $game): ?object
    {
        $bySlug = DB::table('interventions')->where('slug', $game['slug'])->first();

        if ($bySlug !== null) {
            return $bySlug;
        }

        return DB::table('interventions')
            ->whereIn('content_type', ['positive_engagement', 'game'])
            ->where('title', $game['title'])
            ->orderBy('id')
            ->first();
    }
};
