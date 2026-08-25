<?php

namespace App\Services;

use App\Models\Questionnaire;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionnaireActivationService
{
    public function activate(Questionnaire $questionnaire): Questionnaire
    {
        return DB::transaction(function () use ($questionnaire): Questionnaire {
            Questionnaire::query()->where('type', $questionnaire->type)->lockForUpdate()->get();

            $questionnaire->refresh()->load([
                'questions' => fn ($query) => $query->with([
                    'options' => fn ($options) => $options->where('is_active', true)->orderBy('position'),
                ]),
                'scoreBands' => fn ($query) => $query->where('is_active', true)->orderBy('min_score'),
            ]);

            $this->validateConfiguration($questionnaire);

            Questionnaire::query()
                ->where('type', $questionnaire->type)
                ->whereKeyNot($questionnaire->getKey())
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('status', 'published'))
                ->update(['is_active' => false, 'status' => 'archived']);

            $questionnaire->update([
                'is_active' => true,
                'status' => 'published',
                'published_at' => $questionnaire->published_at ?? now(),
            ]);

            return $questionnaire->fresh();
        });
    }

    private function validateConfiguration(Questionnaire $questionnaire): void
    {
        if ($questionnaire->questions->isEmpty()) {
            $this->fail('questions', 'Add at least one question before activation.');
        }
        if ($questionnaire->questions->contains(fn ($question) => ! $question->is_active)) {
            $this->fail('questions', 'Every questionnaire question must be active before activation.');
        }
        if (! $questionnaire->questions->contains(fn ($question) => (bool) $question->pivot->is_required)) {
            $this->fail('questions', 'At least one questionnaire question must be required.');
        }
        if ($questionnaire->questions->pluck('pivot.position')->duplicates()->isNotEmpty()) {
            $this->fail('questions', 'Question positions must be unique before activation.');
        }

        $minimumTotal = 0;
        $maximumTotal = 0;
        foreach ($questionnaire->questions as $question) {
            $options = $question->options;
            if ($options->count() < 2) {
                $this->fail('questions', 'Every active scale question must have at least two active options.');
            }
            if ($options->contains(fn ($option) => $option->score === null)) {
                $this->fail('questions', 'Every active option must have a score before activation.');
            }

            $minimum = (int) $options->min('score');
            $maximum = (int) $options->max('score');
            $minimumTotal += $question->pivot->is_required ? $minimum : min(0, $minimum);
            $maximumTotal += max(0, $maximum);
        }

        $bands = $questionnaire->scoreBands;
        if ($bands->isEmpty()) {
            $this->fail('score_bands', 'Add active score bands before activation.');
        }

        $previousMaximum = null;
        foreach ($bands as $band) {
            if ($band->min_score > $band->max_score) {
                $this->fail('score_bands', 'Score-band minimums must not exceed their maximums.');
            }
            if ($previousMaximum !== null && $band->min_score <= $previousMaximum) {
                $this->fail('score_bands', 'Active score bands must not overlap.');
            }
            $previousMaximum = $band->max_score;
        }

        foreach (range($minimumTotal, $maximumTotal) as $score) {
            if ($bands->where('min_score', '<=', $score)->where('max_score', '>=', $score)->count() !== 1) {
                $this->fail('score_bands', "Active score bands must cover the complete {$minimumTotal}-{$maximumTotal} score range without gaps.");
            }
        }
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
