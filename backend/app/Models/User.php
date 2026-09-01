<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'pseudonymous_uuid', 'account_status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_STUDENT = 'student';

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->pseudonymous_uuid ??= (string) Str::uuid();
        });

        static::created(function (User $user): void {
            if ($user->role === self::ROLE_STUDENT && Schema::hasTable('student_identities')) {
                $user->studentIdentity()->firstOrCreate([], [
                    'pseudonymous_uuid' => $user->pseudonymous_uuid,
                ]);
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isStudent(): bool
    {
        return $this->hasRole(self::ROLE_STUDENT);
    }

    public function hasRole(string $slug): bool
    {
        if ($this->relationLoaded('roles') && $this->roles->contains('slug', $slug)) {
            return true;
        }

        return $this->roles()->where('slug', $slug)->exists() || $this->role === $slug;
    }

    public function assignRole(string $slug): void
    {
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);

        if ($this->role !== $slug) {
            $this->forceFill(['role' => $slug])->saveQuietly();
        }
    }

    public function scopeWithRole(Builder $query, string $slug): Builder
    {
        return $query->where(function (Builder $query) use ($slug): void {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('slug', $slug))
                ->orWhere('role', $slug);
        });
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function studentIdentity(): HasOne
    {
        return $this->hasOne(StudentIdentity::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    public function mfaMethods(): HasMany
    {
        return $this->hasMany(UserMfaMethod::class);
    }

    public function emailOtpChallenges(): HasMany
    {
        return $this->hasMany(EmailOtpChallenge::class);
    }

    public function stressAssessments(): HasMany
    {
        return $this->hasMany(StressAssessment::class);
    }

    public function createdQuestionnaires(): HasMany
    {
        return $this->hasMany(Questionnaire::class, 'created_by_user_id');
    }

    public function interventionUsages(): HasMany
    {
        return $this->hasMany(InterventionUsage::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }
}
