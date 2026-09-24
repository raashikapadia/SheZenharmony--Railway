<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the educational games to the catalogue established by
 * 2026_09_24_000100_establish_game_catalog.
 *
 * The four games that came before are calming exercises: they give a student
 * somewhere to put a difficult moment, but they teach nothing. These four
 * carry the learning — naming thinking traps in a scenario, separating what
 * is true about mental health from what is merely repeated, reading the body
 * for early warning signs, and building the vocabulary to describe any of it.
 *
 * Each row exists so an admin can publish, hide or rename the game; the
 * screen itself ships with the student application and is dispatched on the
 * slug, so a rename never changes what opens.
 */
return new class extends Migration
{
    /**
     * Titles and descriptions are seed values only — an admin edit made after
     * this migration runs is never overwritten.
     *
     * @var list<array{slug: string, title: string, description: string}>
     */
    private const GAMES = [
        [
            'slug' => 'coping-match',
            'title' => 'Coping Match',
            'description' => 'Meet a stressful moment and choose the coping strategy that fits it best.',
        ],
        [
            'slug' => 'myth-or-fact',
            'title' => 'Myth or Fact',
            'description' => 'Decide whether what people say about stress and mental health is true.',
        ],
        [
            'slug' => 'body-signals',
            'title' => 'Body Signals',
            'description' => 'Learn where stress shows up in your body and what each signal is telling you.',
        ],
        [
            'slug' => 'wellbeing-wordsearch',
            'title' => 'Wellbeing Word Search',
            'description' => 'Find the coping words hidden in the grid and learn what each one means.',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::GAMES as $game) {
            // Re-running the migration must not duplicate a game or undo an
            // admin's choice to archive or rename one.
            if (DB::table('interventions')->where('slug', $game['slug'])->exists()) {
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
        // These rows were introduced here and have no earlier section to fall
        // back to, so rolling back takes them away. A game a student has
        // already played keeps its row instead: intervention_usages restricts
        // the delete, and the play history outweighs a tidy rollback.
        $rows = DB::table('interventions')
            ->whereIn('slug', array_column(self::GAMES, 'slug'))
            ->pluck('id');

        $played = DB::table('intervention_usages')
            ->whereIn('intervention_id', $rows)
            ->distinct()
            ->pluck('intervention_id');

        DB::table('interventions')
            ->whereIn('id', $played)
            ->update(['is_active' => false, 'updated_at' => now()]);

        DB::table('interventions')
            ->whereIn('id', $rows->diff($played))
            ->delete();
    }
};
