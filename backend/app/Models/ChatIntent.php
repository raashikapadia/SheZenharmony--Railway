<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category Shezen can recognise, with the keywords that select it.
 *
 * Everything here is authored by an administrator. There is no model, no
 * inference and no generated text anywhere in this feature.
 */
class ChatIntent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_crisis' => 'boolean',
            'is_starter' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ChatResponse::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The admin's keyword list, one phrase per entry, lower-cased for matching.
     *
     * @return array<int, string>
     */
    public function keywordList(): array
    {
        return collect(preg_split('/[,\r\n]+/', (string) $this->keywords))
            ->map(fn (string $keyword): string => trim(mb_strtolower($keyword)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether the student's message contains any of this intent's keywords.
     * Word-boundary matched so "sad" does not fire inside "Saturday".
     */
    public function matches(string $message): bool
    {
        $haystack = mb_strtolower($message);

        foreach ($this->keywordList() as $keyword) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/u', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }
}
