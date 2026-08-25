<?php

namespace App\Http\Requests\Admin;

use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
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
            'question_type' => ['required', Rule::in(QuestionType::values())],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_sensitive' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'options' => ['required', 'array', 'min:2', 'max:20'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.value' => ['required', 'string', 'max:100', 'distinct'],
            'options.*.score' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
        ];
    }
}
