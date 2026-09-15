<?php

namespace App\Support;

use App\Enums\QuestionType;

/**
 * Ready-made answer scales an admin can pick instead of typing option rows.
 *
 * One list serves every place questions are created — the question form,
 * the section form and the bulk "one question per line" box — so the scales
 * students see are consistent wherever a question came from.
 */
final class AnswerScalePresets
{
    /**
     * @return array<string, array{label: string, type: string, options: array<int, array{label: string, value: string, score: int}>}>
     */
    public static function all(): array
    {
        return [
            'agree5' => [
                'label' => 'Agreement, 1–5 (Strongly disagree → Strongly agree)',
                'type' => QuestionType::Scale->value,
                'options' => self::scale([
                    'Strongly disagree', 'Disagree', 'Neutral', 'Agree', 'Strongly agree',
                ]),
            ],
            'frequency5' => [
                'label' => 'Frequency, 1–5 (Never → Always)',
                'type' => QuestionType::Scale->value,
                'options' => self::scale(['Never', 'Rarely', 'Sometimes', 'Often', 'Always']),
            ],
            'often4' => [
                'label' => 'How often, 1–4 (Not at all → Nearly every day)',
                'type' => QuestionType::Scale->value,
                'options' => self::scale([
                    'Not at all', 'Several days', 'More than half the days', 'Nearly every day',
                ]),
            ],
            'yes_no' => [
                'label' => 'Yes / No',
                'type' => QuestionType::YesNo->value,
                'options' => [
                    ['label' => 'No', 'value' => 'no', 'score' => 0],
                    ['label' => 'Yes', 'value' => 'yes', 'score' => 1],
                ],
            ],
        ];
    }

    public const DEFAULT = 'agree5';

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array{label: string, type: string, options: array<int, array{label: string, value: string, score: int}>}
     */
    public static function get(string $key): array
    {
        return self::all()[$key] ?? self::all()[self::DEFAULT];
    }

    public static function slug(string $label): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? '';

        return substr(trim($slug, '_'), 0, 100);
    }

    /**
     * @param  array<int, string>  $labels
     * @return array<int, array{label: string, value: string, score: int}>
     */
    private static function scale(array $labels): array
    {
        $options = [];
        foreach ($labels as $index => $label) {
            $options[] = ['label' => $label, 'value' => self::slug($label), 'score' => $index + 1];
        }

        return $options;
    }
}
