<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Web\AdminTipController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole of what an admin configures for a wellbeing tip: a title, a
 * short description, an optional category, and an optional suggested
 * action. See {@see AdminTipController}.
 */
class TipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        // An unchecked box is simply absent from the payload.
        $this->merge(['is_active' => $this->boolean('is_active')]);

        foreach (['content_category_id', 'steps'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:2000'],
            'content_category_id' => ['nullable', 'integer', 'exists:content_categories,id'],
            'steps' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Give this tip a short title.',
            'content.required' => 'Write the short description students will see.',
        ];
    }
}
