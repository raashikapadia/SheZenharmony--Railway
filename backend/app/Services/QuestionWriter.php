<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\StressQuestion;
use Illuminate\Validation\Rule;

/**
 * Shared persistence + validation for a single assessment question and its
 * options. Used by both the standalone question bank
 * (AdminQuestionController) and section-scoped question management
 * (AdminSectionController) so the two never drift apart.
 */
class QuestionWriter
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'question_text' => ['required', 'string', 'max:2000'],
            'dimension' => ['nullable', 'string', 'max:100'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'question_type' => ['required', Rule::in(QuestionType::values())],
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'is_sensitive' => ['nullable', 'boolean'],
            // Advanced scoring settings — all optional.
            'min_score' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
            'max_score' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
            'wellbeing_weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'is_reverse_scored' => ['nullable', 'boolean'],
            'stress_relevant' => ['nullable', 'boolean'],
            'stress_direction' => ['nullable', Rule::in([
                StressQuestion::STRESS_DIRECTION_MORE, StressQuestion::STRESS_DIRECTION_LESS,
            ])],
            'stress_weight' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'options' => ['required', 'array', 'min:2', 'max:20'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.value' => ['required', 'string', 'max:100', 'distinct'],
            'options.*.score' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
        ];
    }

    /**
     * Fill and save the question, then reconcile its option rows: rows with
     * an id are updated in place (preserving historical references), new
     * rows are created, and any dropped row is deactivated rather than
     * deleted.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(StressQuestion $question, array $data): void
    {
        $stressRelevant = (bool) ($data['stress_relevant'] ?? false);

        $question->fill([
            'question_text' => $data['question_text'],
            'dimension' => $data['dimension'] ?? $question->dimension,
            'help_text' => $data['help_text'] ?? null,
            'question_type' => $data['question_type'],
            'position' => $data['position'] ?? $question->position ?? 0,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_sensitive' => (bool) ($data['is_sensitive'] ?? false),
            'min_score' => $data['min_score'] ?? null,
            'max_score' => $data['max_score'] ?? null,
            'wellbeing_weight' => $data['wellbeing_weight'] ?? 1,
            'is_reverse_scored' => (bool) ($data['is_reverse_scored'] ?? false),
            'stress_relevant' => $stressRelevant,
            'stress_direction' => $stressRelevant
                ? ($data['stress_direction'] ?? StressQuestion::STRESS_DIRECTION_MORE)
                : null,
            'stress_weight' => $data['stress_weight'] ?? 1,
        ])->save();

        $retainedIds = [];
        foreach (array_values($data['options']) as $position => $option) {
            $optionId = $option['id'] ?? null;
            unset($option['id']);
            $values = $option + ['position' => $position + 1, 'is_active' => true];

            if ($optionId !== null) {
                $existing = $question->options()->whereKey($optionId)->firstOrFail();
                $existing->update($values);
                $retainedIds[] = $existing->id;
            } else {
                $retainedIds[] = $question->options()->create($values)->id;
            }
        }

        $question->options()->whereNotIn('id', $retainedIds)->update(['is_active' => false]);
    }
}
