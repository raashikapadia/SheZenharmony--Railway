<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Web\AdminGuidanceController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole of what an admin configures for a piece of coping-strategy
 * advice: a title, the advice itself, an optional category, an optional
 * "try this" action, and an optional external resource link. No matching
 * rules to configure — see {@see AdminGuidanceController}.
 */
class GuidanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        // An unchecked box is simply absent from the payload.
        $this->merge(['is_active' => $this->boolean('is_active')]);

        foreach (['content_category_id', 'steps', 'resource_url'] as $key) {
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
            'resource_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Give this guidance a short title.',
            'content.required' => 'Write the advice students will see.',
            'resource_url.url' => 'Enter a full web address, starting with https://',
        ];
    }
}
