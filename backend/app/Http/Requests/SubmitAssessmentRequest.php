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
            'answers.*' => ['required', 'array:question_id,option_id'],
            'answers.*.question_id' => ['required', 'integer', 'min:1', 'distinct'],
            'answers.*.option_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['prohibited'],
            'total_score' => ['prohibited'],
            'stress_score_band_id' => ['prohibited'],
            'stress_level' => ['prohibited'],
            'question_text_snapshot' => ['prohibited'],
            'option_text_snapshot' => ['prohibited'],
        ];
    }
}
