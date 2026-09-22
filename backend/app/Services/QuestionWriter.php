<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\StressQuestion;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Shared persistence + validation for a single assessment question and its
 * options. Used by both the standalone question bank
 * (AdminQuestionController) and section-scoped question management
 * (AdminSectionController) so the two never drift apart.
 *
 * A question is described by four independent settings — how it is shown
 * (`question_type`), how it is answered (`answer_mode` + `max_selections`),
 * how the answer becomes points (`scoring_method`) and which way the scale
 * runs (`is_reverse_scored`) — plus its options, each with its own points.
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
            'answer_mode' => ['nullable', Rule::in(StressQuestion::answerModes())],
            'max_selections' => ['nullable', 'integer', 'min:1', 'max:50'],
            'scoring_method' => ['nullable', Rule::in(StressQuestion::scoringMethods())],
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
        $answerMode = $data['answer_mode'] ?? StressQuestion::ANSWER_SINGLE;
        $multiple = $answerMode === StressQuestion::ANSWER_MULTIPLE;

        // A True/False or rating-scale question is one answer by nature; only
        // multiple choice can take several ticks.
        if ($multiple && ($data['question_type'] ?? null) !== QuestionType::MultipleChoice->value) {
            throw ValidationException::withMessages([
                'answer_mode' => 'Only a multiple choice question can allow several answers.',
            ]);
        }

        $optionCount = count($data['options'] ?? []);
        $maxSelections = $multiple && isset($data['max_selections']) ? (int) $data['max_selections'] : null;
        if ($maxSelections !== null && $maxSelections > $optionCount) {
            throw ValidationException::withMessages([
                'max_selections' => "Students can't pick {$maxSelections} answers when the question only has {$optionCount}.",
            ]);
        }

        $question->fill([
            'question_text' => $data['question_text'],
            'dimension' => $data['dimension'] ?? $question->dimension,
            'help_text' => $data['help_text'] ?? null,
            'question_type' => $data['question_type'],
            'answer_mode' => $multiple ? StressQuestion::ANSWER_MULTIPLE : StressQuestion::ANSWER_SINGLE,
            'max_selections' => $maxSelections,
            'scoring_method' => $multiple
                ? ($data['scoring_method'] ?? StressQuestion::SCORING_DIRECT)
                : StressQuestion::SCORING_DIRECT,
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
