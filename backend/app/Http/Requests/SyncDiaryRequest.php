<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a whole diary pushed up from a device.
 *
 * The nesting is why this is a Form Request rather than an inline
 * `$request->validate()`: a diary carries its pages, and every one of them
 * needs the same shape check before the merge starts writing.
 */
class SyncDiaryRequest extends FormRequest
{
    /**
     * Authorisation is the `auth:sanctum` + `student.active` middleware on the
     * route, and the merge only ever touches the caller's own identity.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'diaries' => ['present', 'array', 'max:200'],
            'diaries.*.client_id' => ['required', 'string', 'max:64'],
            'diaries.*.title' => ['present', 'nullable', 'string', 'max:200'],
            'diaries.*.cover_index' => ['required', 'integer', 'min:0', 'max:1000'],
            'diaries.*.deleted' => ['sometimes', 'boolean'],
            'diaries.*.created_at' => ['required', 'date'],
            'diaries.*.updated_at' => ['required', 'date'],

            // Whether this diary asks for the student's PIN. Which PIN is not
            // a property of the diary — a student has exactly one, below.
            'diaries.*.is_locked' => ['sometimes', 'boolean'],

            'diaries.*.pages' => ['present', 'array', 'max:2000'],
            'diaries.*.pages.*.client_id' => ['required', 'string', 'max:64'],
            'diaries.*.pages.*.title' => ['present', 'nullable', 'string', 'max:200'],
            'diaries.*.pages.*.body' => ['present', 'nullable', 'string', 'max:100000'],
            'diaries.*.pages.*.deleted' => ['sometimes', 'boolean'],
            'diaries.*.pages.*.created_at' => ['required', 'date'],
            'diaries.*.pages.*.updated_at' => ['required', 'date'],

            // The student's single PIN. Absent means this device has nothing to
            // say about it, which must never be read as "there is no PIN" —
            // only an explicit `cleared` removes one.
            'lock' => ['sometimes', 'nullable', 'array'],
            'lock.updated_at' => ['required_with:lock', 'date'],
            'lock.cleared' => ['sometimes', 'boolean'],
            'lock.salt' => ['nullable', 'string', 'max:255'],
            'lock.hash' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * A lock must carry a PIN unless it is the instruction to remove one.
     *
     * Expressed here rather than as `required_with`, because clearing a PIN
     * sends an explicit null salt and hash, which `required_with` rejects.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lock = $this->input('lock');
            if (! is_array($lock) || ($lock['cleared'] ?? false)) {
                return;
            }

            foreach (['salt', 'hash'] as $field) {
                if (($lock[$field] ?? null) === null || $lock[$field] === '') {
                    $validator->errors()->add(
                        "lock.$field",
                        "The lock.$field field is required unless the PIN is being cleared.",
                    );
                }
            }
        });
    }
}
