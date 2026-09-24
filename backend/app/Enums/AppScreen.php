<?php

namespace App\Enums;

/**
 * The SheZen Harmony screens a recommended intervention can send a student to.
 *
 * Each case is a screen the app already has. The admin picks one when
 * publishing support content, and the app opens that screen when a student
 * taps the recommendation, instead of leaving the app for a browser link.
 *
 * Values carry a `screen.` prefix so a destination is never mistaken for a
 * content type: the two vocabularies overlap ("journaling",
 * "positive_engagement" are both), and they are stored and posted side by
 * side on the same form.
 *
 * Adding a case here only makes it offerable: the app maps the same keys to
 * its screens (see `intervention_destination.dart` in the Flutter assessment
 * feature), so both sides must know a key for it to be useful.
 */
enum AppScreen: string
{
    case WellbeingActivities = 'screen.wellbeing_activities';
    case Journaling = 'screen.journaling';
    case PersonalGuidance = 'screen.personal_guidance';
    case PositiveEngagement = 'screen.positive_engagement';
    case GamesAndQuizzes = 'screen.games_quizzes';
    case Resources = 'screen.resources';
    case ChatBuddy = 'screen.chat_buddy';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /** What an admin sees in the destination picker. */
    public function label(): string
    {
        return match ($this) {
            self::WellbeingActivities => 'Wellbeing Activities',
            self::Journaling => 'Journaling (Diary)',
            self::PersonalGuidance => 'Personal Guidance',
            self::PositiveEngagement => 'Positive Engagement',
            self::GamesAndQuizzes => 'Games & Quizzes',
            self::Resources => 'Resources',
            self::ChatBuddy => 'Chat Buddy',
        };
    }
}
