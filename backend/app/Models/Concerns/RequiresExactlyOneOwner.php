<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait RequiresExactlyOneOwner
{
    protected static function bootRequiresExactlyOneOwner(): void
    {
        static::creating(function (Model $model): void {
            $hasRegisteredOwner = $model->getAttribute('user_id') !== null;
            $hasAnonymousOwner = collect($model->anonymousOwnerColumns())
                ->contains(fn (string $column): bool => $model->getAttribute($column) !== null);

            if ($hasRegisteredOwner === $hasAnonymousOwner) {
                throw ValidationException::withMessages([
                    'owner' => 'Exactly one registered or anonymous owner is required when creating this record.',
                ]);
            }
        });
    }

    /** @return list<string> */
    protected function anonymousOwnerColumns(): array
    {
        return ['anonymous_session_id'];
    }
}
