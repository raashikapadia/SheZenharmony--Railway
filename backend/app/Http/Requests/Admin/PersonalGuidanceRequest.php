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
        foreach (['author', 'category', 'publish_at', 'expires_at'] as $key) {
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
