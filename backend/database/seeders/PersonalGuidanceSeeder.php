<?php

namespace Database\Seeders;

use App\Models\PersonalGuidance;
use Illuminate\Database\Seeder;

/**
 * A small curated starter set so the Home Page has something to show on a
 * fresh database. Guarded: does nothing once any Personal Guidance exists,
 * so it never fights admin-managed content.
 *
 * Affirmations and admin guidance below are original text written for SheZen.
 * Quote attributions follow the most widely published sourcing; admins can
 * correct the wording, the author, or remove any item from the Personal
 * Guidance admin screen without an app release.
 */
class PersonalGuidanceSeeder extends Seeder
{
    public function run(): void
    {
        if (PersonalGuidance::query()->exists()) {
            return;
        }

        $affirmations = [
            ['I am capable of handling whatever today brings.', 'Confidence'],
            ['I am allowed to take up space, to rest, and to begin again.', 'Self-Belief'],
            ['My pace is my own, and steady progress still counts.', 'Personal Growth'],
            ['I can ask for help without it meaning I have failed.', 'Resilience'],
            ['I have moved through hard days before, and I carry that strength now.', 'Resilience'],
            ['I am worthy of the same kindness I offer everyone else.', 'Self-Belief'],
        ];

        $guidance = [
            ["Don't compare your chapter one to someone else's chapter ten. Keep going. \u{1F337}", 'Personal Growth'],
            ["You don't have to have everything figured out right now. One honest step is enough for today.", 'Motivation'],
            ['Rest is not a reward for finishing — it is part of how you get there.', 'Life'],
        ];

        $quotes = [
            ['It always seems impossible until it is done.', 'Nelson Mandela', 'Resilience'],
            ['The future belongs to those who believe in the beauty of their dreams.', 'Eleanor Roosevelt', 'Motivation'],
            ['We may encounter many defeats but we must not be defeated.', 'Maya Angelou', 'Resilience'],
            ['Believe you can and you are halfway there.', 'Theodore Roosevelt', 'Confidence'],
        ];

        $tips = [
            ['Drink some water', 'Staying hydrated helps your focus and your mood — keep a bottle nearby today.', 'Wellbeing'],
            ['Take a short break', 'A five minute pause between tasks helps you come back with a clearer head.', 'Wellbeing'],
            ['Step outside for a moment', 'A little daylight and fresh air can lift your mood more than you expect.', 'Wellbeing'],
            ['Practice deep breathing', 'Three slow breaths can take the edge off a stressful moment.', 'Wellbeing'],
        ];

        foreach ($affirmations as [$content, $category]) {
            $this->create(PersonalGuidance::TYPE_AFFIRMATION, $content, null, $category);
        }

        foreach ($guidance as [$content, $category]) {
            $this->create(PersonalGuidance::TYPE_GUIDANCE, $content, null, $category);
        }

        foreach ($quotes as [$content, $author, $category]) {
            $this->create(PersonalGuidance::TYPE_QUOTE, $content, $author, $category);
        }

        foreach ($tips as [$title, $content, $category]) {
            $this->create(PersonalGuidance::TYPE_TIP, $content, null, $category, $title);
        }
    }

    private function create(string $type, string $content, ?string $author, string $category, ?string $title = null): void
    {
        PersonalGuidance::query()->create([
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'author' => $author,
            'category' => $category,
            'status' => PersonalGuidance::STATUS_PUBLISHED,
            'publish_at' => null,
            'expires_at' => null,
        ]);
    }
}
