<?php

namespace App\Http\Requests\Admin;

use App\Enums\QuestionType;
use App\Models\StressQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'question_text' => ['required', 'string', 'max:2000'],
            'dimension' => ['nullable', 'string', 'max:100'],
            'help_text' => ['nullable', 'string', 'max:1000'],
            'question_type' => ['required', Rule::in(QuestionType::values())],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_sensitive' => ['nullable', 'boolean'],
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
}
