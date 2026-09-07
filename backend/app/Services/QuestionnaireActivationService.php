<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\QuestionnaireSection;
use App\Models\StressScoreBand;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionnaireActivationService
{
    public function activate(Questionnaire $questionnaire): Questionnaire
    {
        return DB::transaction(function () use ($questionnaire): Questionnaire {
            Questionnaire::query()->where('type', $questionnaire->type)->lockForUpdate()->get();

            $questionnaire->refresh()->load([
                'sections' => fn ($query) => $query->where('is_active', true)->orderBy('position'),
                'questions' => fn ($query) => $query->with([
                    'options' => fn ($options) => $options->where('is_active', true)->orderBy('position'),
                ]),
                'scoreBands' => fn ($query) => $query->where('is_active', true)->orderBy('min_score'),
            ]);

            $this->validateConfiguration($questionnaire);

            // Only one questionnaire of a type is ever live. Publishing this
            // one turns every other (non-trashed) version back to a draft.
            Questionnaire::query()
                ->where('type', $questionnaire->type)
                ->whereKeyNot($questionnaire->getKey())
                ->whereNull('trashed_at')
                ->update(['is_active' => false, 'status' => 'draft']);

            $questionnaire->update([
                'is_active' => true,
                'status' => 'published',
                'published_at' => $questionnaire->published_at ?? now(),
            ]);

            return $questionnaire->fresh();
        });
    }

    /**
     * Run the same checks activation would, without changing any state.
     * Throws ValidationException with plain-language messages on the first
     * problem found; returns quietly when the questionnaire is publishable.
     */
    public function preflight(Questionnaire $questionnaire): void
    {
        $questionnaire->loadMissing([
            'sections' => fn ($query) => $query->where('is_active', true)->orderBy('position'),
            'questions' => fn ($query) => $query->with([
                'options' => fn ($options) => $options->where('is_active', true)->orderBy('position'),
            ]),
            'scoreBands' => fn ($query) => $query->where('is_active', true)->orderBy('min_score'),
        ]);

        $this->validateConfiguration($questionnaire);
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

        foreach ($questionnaire->questions as $question) {
            $options = $question->options;
            if ($options->count() < 2) {
                $this->fail('questions', 'Every active scale question must have at least two active options.');
            }
            if ($options->contains(fn ($option) => $option->score === null)) {
                $this->fail('questions', 'Every active option must have a score before activation.');
            }
        }

        $activeSections = $questionnaire->sections->where('is_active', true);

        if ($activeSections->isNotEmpty()) {
            $this->validateSectioned($questionnaire, $activeSections);

            return;
        }

        $this->validateFlat($questionnaire);
    }

    /**
     * Historical flat questionnaire: one 'overall' band set must cover the
     * full possible raw-total range with no gaps or overlaps.
     */
    private function validateFlat(Questionnaire $questionnaire): void
    {
        $minimumTotal = 0;
        $maximumTotal = 0;
        foreach ($questionnaire->questions as $question) {
            $options = $question->options;
            $minimum = (int) $options->min('score');
            $maximum = (int) $options->max('score');
            $minimumTotal += $question->pivot->is_required ? $minimum : min(0, $minimum);
            $maximumTotal += max(0, $maximum);
        }

        $bands = $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_OVERALL)->values();
        if ($bands->isEmpty()) {
            $this->fail('score_bands', 'Add active score bands before activation.');
        }

        $this->assertNoOverlap($bands, 'score_bands');

        foreach (range($minimumTotal, $maximumTotal) as $score) {
            if ($bands->where('min_score', '<=', $score)->where('max_score', '>=', $score)->count() !== 1) {
                $this->fail('score_bands', "Active score bands must cover the complete {$minimumTotal}-{$maximumTotal} score range without gaps.");
            }
        }
    }

    /**
     * Dynamic wellbeing questionnaire: sections carry the weights, so the
     * 'overall' bands are validated against the weighted 0-Σweight range and
     * (when any question feeds the stress sub-score) the 'stress' bands
     * against 0-100.
     *
     * @param  Collection<int, QuestionnaireSection>  $activeSections
     */
    private function validateSectioned(Questionnaire $questionnaire, Collection $activeSections): void
    {
        $sectionIds = $activeSections->pluck('id')->map(fn ($id) => (int) $id);

        foreach ($questionnaire->questions as $question) {
            $sectionId = $question->pivot->questionnaire_section_id;
            if ($sectionId === null || ! $sectionIds->contains((int) $sectionId)) {
                $this->fail('sections', 'Every question must be placed in an active section before this questionnaire can go live.');
            }
        }

        foreach ($activeSections as $section) {
            $count = $questionnaire->questions
                ->filter(fn ($question) => (int) $question->pivot->questionnaire_section_id === (int) $section->id)
                ->count();
            if ($count === 0) {
                $this->fail('sections', "Section \"{$section->title}\" has no active questions. Add a question or archive the section.");
            }
            if ((float) $section->category_weight <= 0) {
                $this->fail('sections', "Section \"{$section->title}\" needs a category weight greater than 0.");
            }
        }

        $sumWeights = (float) $activeSections->sum('category_weight');
        $overallBands = $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_OVERALL)->values();
        if ($overallBands->isEmpty()) {
            $this->fail('score_bands', 'Add overall wellbeing result ranges before activation.');
        }
        $this->assertCoversRange($overallBands, 0, (int) ceil($sumWeights), 'wellbeing result ranges');

        $hasStressQuestions = $questionnaire->questions->contains(fn ($question) => (bool) $question->stress_relevant);
        if ($hasStressQuestions) {
            $stressBands = $questionnaire->scoreBands->where('scope', StressScoreBand::SCOPE_STRESS)->values();
            if ($stressBands->isEmpty()) {
                $this->fail('score_bands', 'This questionnaire has stress-relevant questions, so it needs stress result ranges (0-100).');
            }
            $this->assertCoversRange($stressBands, 0, 100, 'stress result ranges');
        }
    }

    /**
     * @param  Collection<int, StressScoreBand>  $bands
     */
    private function assertNoOverlap(Collection $bands, string $key): void
    {
        $sorted = $bands->sortBy('min_score')->values();
        $previousMax = null;
        foreach ($sorted as $band) {
            if ($band->min_score > $band->max_score) {
                $this->fail($key, 'A result range has a minimum higher than its maximum. Please correct it.');
            }
            if ($previousMax !== null && $band->min_score <= $previousMax) {
                $this->fail($key, 'Two result ranges overlap. Please adjust the minimum or maximum score.');
            }
            $previousMax = $band->max_score;
        }
    }

    /**
     * @param  Collection<int, StressScoreBand>  $bands
     */
    private function assertCoversRange(Collection $bands, int $from, int $to, string $label): void
    {
        $this->assertNoOverlap($bands, 'score_bands');
        $sorted = $bands->sortBy('min_score')->values();

        if ($sorted->first()->min_score > $from) {
            $this->fail('score_bands', ucfirst($label)." must start at {$from}. There is a gap below the lowest range.");
        }
        if ($sorted->last()->max_score < $to) {
            $this->fail('score_bands', ucfirst($label)." must reach {$to}. There is a gap above the highest range.");
        }

        $previousMax = null;
        foreach ($sorted as $band) {
            if ($previousMax !== null && $band->min_score > $previousMax + 1) {
                $this->fail('score_bands', "There is a gap in the {$label} between {$previousMax} and {$band->min_score}.");
            }
            $previousMax = $band->max_score;
        }
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
