<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentSubmissionService
{
    public function __construct(private readonly AssessmentScoringService $scoringService) {}

    /**
     * @param list<array{question_id: int, option_id: int}> $submittedAnswers
     * @return array{assessment: StressAssessment, score_band: \App\Models\StressScoreBand}
     */
    public function submit(User $user, int $questionnaireId, array $submittedAnswers): array
    {
        return DB::transaction(function () use ($user, $questionnaireId, $submittedAnswers): array {
            $questionnaire = Questionnaire::query()->lockForUpdate()->find($questionnaireId);
            $this->assertAvailable($questionnaire);

            $answers = collect($submittedAnswers);
            if ($answers->pluck('question_id')->unique()->count() !== $answers->count()) {
                throw ValidationException::withMessages(['answers' => 'A question may only be answered once.']);
            }

            $answerMap = $answers->mapWithKeys(fn (array $answer): array => [
                (int) $answer['question_id'] => (int) $answer['option_id'],
            ])->all();

            $scored = $this->scoringService->score($questionnaire, $answerMap);

            $questions = $questionnaire->questions()
                ->whereIn('stress_questions.id', array_keys($answerMap))
                ->with(['options' => fn ($query) => $query->whereIn('id', array_values($answerMap))])
                ->get()->keyBy('id');

            $completedAt = now();
            $assessment = StressAssessment::query()->create([
                'questionnaire_id' => $questionnaire->id,
                'user_id' => $user->id,
                'stress_score_band_id' => $scored['score_band']->id,
                'assessment_type' => $questionnaire->type,
                'assessment_status' => 'completed',
                'total_score' => $scored['total_score'],
                'stress_level' => $scored['score_band']->label,
                'started_at' => $completedAt,
                'completed_at' => $completedAt,
            ]);

            foreach ($answerMap as $questionId => $optionId) {
                $question = $questions->get($questionId);
                $option = $question?->options->firstWhere('id', $optionId);

                // Scoring validated these rows in the same transaction; this is a defensive guard.
                if (! $question || ! $option) {
                    throw ValidationException::withMessages(['answers' => 'Questionnaire configuration changed during submission. Please retry.']);
                }

                $assessment->responses()->create([
                    'stress_question_id' => $question->id,
                    'question_option_id' => $option->id,
                    'score' => $option->score,
                    'question_text_snapshot' => $question->question_text,
                    'option_text_snapshot' => $option->label,
                ]);
            }

            return ['assessment' => $assessment, 'score_band' => $scored['score_band']];
        });
    }

    private function assertAvailable(?Questionnaire $questionnaire): void
    {
        if (! $questionnaire) {
            throw ValidationException::withMessages(['questionnaire_id' => 'The selected questionnaire does not exist.']);
        }
        if ($questionnaire->status !== 'published') {
            throw ValidationException::withMessages(['questionnaire_id' => 'The selected questionnaire is not published.']);
        }
        if (! $questionnaire->is_active) {
            throw ValidationException::withMessages(['questionnaire_id' => 'The selected questionnaire is not active.']);
        }
        if ($questionnaire->published_at?->isFuture()) {
            throw ValidationException::withMessages(['questionnaire_id' => 'The selected questionnaire is not yet available.']);
        }
    }
}
