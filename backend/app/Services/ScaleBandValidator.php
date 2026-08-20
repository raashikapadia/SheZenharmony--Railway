<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\StressScoreBand;
use Illuminate\Validation\ValidationException;

class ScaleBandValidator
{
    public function validateCollection(array $bands): void
    {
        $active = collect($bands)->filter(fn (array $band) => (bool) ($band['is_active'] ?? false))->values();
        foreach ($active as $index => $band) {
            if ((int) $band['min_score'] > (int) $band['max_score']) {
                throw ValidationException::withMessages(["bands.{$index}.min_score" => 'The minimum score must not exceed the maximum score.']);
            }
            foreach ($active->slice($index + 1) as $other) {
                if ((int) $band['min_score'] <= (int) $other['max_score'] && (int) $band['max_score'] >= (int) $other['min_score']) {
                    throw ValidationException::withMessages(['bands' => 'Active score bands for a questionnaire may not overlap.']);
                }
            }
        }
    }

    public function validate(Questionnaire $questionnaire, array $attributes, ?StressScoreBand $except = null): void
    {
        $min = (int) $attributes['min_score'];
        $max = (int) $attributes['max_score'];

        if ($min > $max) {
            throw ValidationException::withMessages(['min_score' => 'The minimum score must not exceed the maximum score.']);
        }

        if (! ($attributes['is_active'] ?? true)) {
            return;
        }

        $overlaps = $questionnaire->scoreBands()
            ->where('is_active', true)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->where('min_score', '<=', $max)
            ->where('max_score', '>=', $min)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['min_score' => 'Active score bands for a questionnaire may not overlap.']);
        }
    }
}
