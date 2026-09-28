<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ContentCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The admin form only ever asks for a name — a slug is a developer
        // concern, not something to expose as a field. Generated once, on
        // create, and never silently changed under an admin's feet afterwards.
        static::creating(function (ContentCategory $category): void {
            if (($category->slug ?? '') !== '') {
                return;
            }

            $base = Str::slug($category->name) ?: 'category';
            $slug = $base;
            $suffix = 2;
            while (static::query()->where('slug', $slug)->exists()) {
                $slug = "{$base}-{$suffix}";
                $suffix++;
            }
            $category->slug = $slug;
        });
    }

    public function interventions(): BelongsToMany
    {
        return $this->belongsToMany(Intervention::class, 'intervention_content_categories')->withTimestamps();
    }

    /** Tips, quotes, and affirmations currently filed under this category. */
    public function personalGuidance(): HasMany
    {
        return $this->hasMany(PersonalGuidance::class);
    }
}
