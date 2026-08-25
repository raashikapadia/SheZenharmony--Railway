<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScoreBandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('stress_score_bands')->where(fn ($q) => $q->where('questionnaire_id', $this->route('questionnaire')->id)),
            ],
            'label' => ['required', 'string', 'max:100'],
            'min_score' => ['required', 'integer'],
            'max_score' => ['required', 'integer', 'gte:min_score'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
