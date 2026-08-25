<?php

namespace App\Enums;

/**
 * The only question type the SheZen Harmony assessment engine supports today.
 * AssessmentScoringService assumes every question resolves to a single scored
 * QuestionOption, so new types cannot be added here without also extending
 * that scoring logic.
 */
enum QuestionType: string
{
    case Scale = 'scale';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Scale => 'Scale',
        };
    }
}
