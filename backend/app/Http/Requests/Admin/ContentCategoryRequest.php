<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }

        // Unchecked boxes are absent from the payload.
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $category = $this->route('contentCategory');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('content_categories', 'name')->ignore($category),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A category with this name already exists.',
        ];
    }
}
