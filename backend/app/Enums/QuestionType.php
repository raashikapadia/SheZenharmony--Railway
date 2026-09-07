<?php

namespace App\Enums;

/**
 * Question types the SheZen Harmony assessment engine supports.
 *
 * All current types resolve to a single scored QuestionOption, so
 * AssessmentScoringService treats them identically. A future `Slider` type
 * would carry a numeric response instead of an option and must extend both
 * the response schema and the scoring logic before it can be added here.
 */
enum QuestionType: string
{
    case Scale = 'scale';
    case MultipleChoice = 'multiple_choice';
    case YesNo = 'yes_no';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Scale => 'Rating scale',
            self::MultipleChoice => 'Multiple choice',
            self::YesNo => 'Yes / No',
        };
    }
}
