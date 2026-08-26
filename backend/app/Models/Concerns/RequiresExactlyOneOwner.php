<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait RequiresExactlyOneOwner
{
    protected static function bootRequiresExactlyOneOwner(): void
    {
        static::creating(function (Model $model): void {
            $registeredOwners = collect($model->registeredOwnerColumns())
                ->filter(fn (string $column): bool => $model->getAttribute($column) !== null);
            $hasRegisteredOwner = $registeredOwners->isNotEmpty();
            $hasAnonymousOwner = collect($model->anonymousOwnerColumns())
                ->contains(fn (string $column): bool => $model->getAttribute($column) !== null);

            if ($registeredOwners->count() > 1 || $hasRegisteredOwner === $hasAnonymousOwner) {
                throw ValidationException::withMessages([
                    'owner' => 'Exactly one registered or anonymous owner is required when creating this record.',
                ]);
            }
        });
    }

    /** @return list<string> */
    protected function registeredOwnerColumns(): array
    {
        return ['student_identity_id', 'user_id'];
    }

    /** @return list<string> */
    protected function anonymousOwnerColumns(): array
    {
        return ['anonymous_session_id'];
    }
}
