<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class HelplineResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $optional = [
            'organisation', 'description', 'alternate_phone',
            'email', 'website_url', 'availability', 'category',
        ];

        foreach ($optional as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        // Unchecked boxes are absent from the payload, and `position` is
        // optional on the form.
        $this->merge([
            'is_emergency' => $this->boolean('is_emergency'),
            'is_active' => $this->boolean('is_active'),
            'position' => (int) $this->input('position', 0),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'organisation' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // Kept as a loose string: helpline numbers are short codes as
            // often as they are dialable numbers ("1325", "+679 999 1234").
            'phone' => ['required', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:2000'],
            'availability' => ['nullable', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_emergency' => ['boolean'],
            'position' => ['integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'A helpline needs a contact number.',
        ];
    }
}
