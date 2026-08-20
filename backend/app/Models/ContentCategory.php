<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContentCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function interventions(): BelongsToMany
    {
        return $this->belongsToMany(Intervention::class, 'intervention_content_categories')->withTimestamps();
    }
}
