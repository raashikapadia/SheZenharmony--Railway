<?php

namespace App\Http\Requests\Admin;

use App\Models\PersonalGuidance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonalGuidanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $optional = [
            'author', 'category', 'publish_at', 'expires_at',
            'title', 'summary', 'when_it_helps', 'steps',
            'duration_minutes', 'related_intervention_id',
        ];

        foreach ($optional as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(PersonalGuidance::TYPES)],
            'content' => ['required', 'string', 'max:2000'],
            'author' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->input('type') === PersonalGuidance::TYPE_QUOTE)],
            'category' => ['nullable', 'string', 'max:100'],
            // Optional coping-strategy fields. A guidance item stays valid with
            // none of them set, which keeps existing content editable as-is.
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'when_it_helps' => ['nullable', 'string', 'max:500'],
            'steps' => ['nullable', 'string', 'max:4000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'related_intervention_id' => ['nullable', 'integer', 'exists:interventions,id'],
            // Matching rules: which assessment outcomes this guidance suits.
            'band_ids' => ['nullable', 'array'],
            'band_ids.*' => ['integer', 'exists:stress_score_bands,id'],
            'section_ids' => ['nullable', 'array'],
            'section_ids.*' => ['integer', 'exists:questionnaire_sections,id'],
            'status' => ['required', Rule::in(PersonalGuidance::STATUSES)],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => array_values(array_filter([
                'nullable', 'date',
                $this->filled('publish_at') ? 'after:publish_at' : null,
            ])),
        ];
    }

    public function messages(): array
    {
        return [
            'author.required' => 'A motivational quote needs an author.',
            'expires_at.after' => 'The expiry must be later than the publish date.',
        ];
    }
}
