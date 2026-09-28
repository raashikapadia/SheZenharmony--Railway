<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Web\AdminPersonalGuidanceController;
use App\Models\PersonalGuidance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What an admin configures for a Daily Affirmation or a motivational quote:
 * the text, an optional author, an optional category, and its status. No
 * scheduling and no matching rule to configure — see
 * {@see AdminPersonalGuidanceController}.
 */
class PersonalGuidanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['author', 'category', 'content_category_id'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        // An unchecked box is simply absent from the payload.
        $this->merge(['is_active' => $this->boolean('is_active')]);

        // Left blank on the form is "no preference" (0) rather than absent.
        $this->merge(['position' => (int) $this->input('position', 0)]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([PersonalGuidance::TYPE_AFFIRMATION, PersonalGuidance::TYPE_QUOTE])],
            'content' => ['required', 'string', 'max:2000'],
            'author' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->input('type') === PersonalGuidance::TYPE_QUOTE)],
            'content_category_id' => ['nullable', 'integer', 'exists:content_categories,id'],
            'position' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'author.required' => 'A motivational quote needs an author.',
        ];
    }
}
