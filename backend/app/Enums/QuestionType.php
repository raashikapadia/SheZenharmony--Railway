<?php

namespace App\Enums;

/**
 * Question types the SheZen Harmony assessment engine supports.
 *
 * The type says how a question is *presented*. How it is answered (one
 * option or several) and how the answer becomes points are separate
 * settings on the question — `answer_mode` and `scoring_method` — so the
 * same type can be configured differently from one question to the next.
 * Every type stores its answers as scored QuestionOption rows.
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
            self::YesNo => 'True / False (Yes / No)',
        };
    }
}
