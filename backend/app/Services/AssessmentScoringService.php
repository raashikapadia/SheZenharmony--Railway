<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\QuestionOption;
use App\Models\StressScoreBand;
use Illuminate\Validation\ValidationException;

class AssessmentScoringService
{
    /** @return array{total_score: int, score_band: StressScoreBand} */
    public function score(Questionnaire $questionnaire, array $answers): array
    {
        $memberships = $questionnaire->questions()
            ->where('stress_questions.is_active', true)
            ->get()
            ->keyBy('id');

        $unknownQuestions = collect(array_keys($answers))->diff($memberships->keys());
        if ($unknownQuestions->isNotEmpty()) {
            throw ValidationException::withMessages(['answers' => 'An answer references a question outside this questionnaire.']);
        }

        $missingRequired = $memberships
            ->filter(fn ($question) => $question->pivot->is_required && ! array_key_exists($question->id, $answers));
        if ($missingRequired->isNotEmpty()) {
            throw ValidationException::withMessages(['answers' => 'All required questionnaire questions must be answered.']);
        }

        $total = 0;
        foreach ($answers as $questionId => $optionId) {
            $option = QuestionOption::query()
                ->whereKey($optionId)
                ->where('stress_question_id', $questionId)
                ->where('is_active', true)
                ->first();

            if (! $option || $option->score === null) {
                throw ValidationException::withMessages(['answers' => 'An answer option is invalid, inactive, or not scorable.']);
            }
            $total += $option->score;
        }

        $band = $questionnaire->scoreBands()
            ->where('is_active', true)
            ->where('min_score', '<=', $total)
            ->where('max_score', '>=', $total)
            ->orderBy('position')
            ->first();

        if (! $band) {
            throw ValidationException::withMessages(['score_bands' => "No active score band is configured for total score {$total}."]);
        }

        return ['total_score' => $total, 'score_band' => $band];
    }
}
