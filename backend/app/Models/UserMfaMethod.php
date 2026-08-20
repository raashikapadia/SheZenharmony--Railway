<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMfaMethod extends Model
{
    protected $fillable = ['user_id', 'method', 'secret_encrypted', 'recovery_codes_encrypted', 'is_active', 'confirmed_at', 'last_used_at'];
    protected $hidden = ['secret_encrypted', 'recovery_codes_encrypted'];

    protected function casts(): array
    {
        return [
            'secret_encrypted' => 'encrypted',
            'recovery_codes_encrypted' => 'encrypted:array',
            'is_active' => 'boolean',
            'confirmed_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
