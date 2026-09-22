<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'questionnaire_id' => ['required', 'integer', 'min:1'],
            'answers' => ['required', 'array', 'min:1'],
            // One answer per question: either a single `option_id` or, for a
            // multi-select question, a list of `option_ids`. The engine
            // checks the choice against the question's own answer mode.
            'answers.*' => ['required', 'array:question_id,option_id,option_ids'],
            'answers.*.question_id' => ['required', 'integer', 'min:1', 'distinct'],
            'answers.*.option_id' => ['required_without:answers.*.option_ids', 'nullable', 'integer', 'min:1'],
            'answers.*.option_ids' => ['required_without:answers.*.option_id', 'nullable', 'array', 'min:1', 'max:50'],
            'answers.*.option_ids.*' => ['integer', 'min:1', 'distinct'],
            'user_id' => ['prohibited'],
            'total_score' => ['prohibited'],
            'stress_score_band_id' => ['prohibited'],
            'stress_level' => ['prohibited'],
            'question_text_snapshot' => ['prohibited'],
            'option_text_snapshot' => ['prohibited'],
        ];
    }
}
