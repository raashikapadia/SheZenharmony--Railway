<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\StressAssessment;
use App\Models\StressScoreBand;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentSubmissionService
{
    public function __construct(private readonly AssessmentScoringService $scoringService) {}

    /**
     * @param  list<array{question_id: int, option_id: int}>  $submittedAnswers
     * @return array{assessment: StressAssessment, score_band: StressScoreBand, scored: array<string, mixed>}
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
            $band = $scored['score_band'];
            $breakdown = $scored['breakdown'] ?? null;

            $completedAt = now();
            $assessment = StressAssessment::query()->create([
                'questionnaire_id' => $questionnaire->id,
                'student_identity_id' => $user->studentIdentity()->firstOrFail()->id,
                'stress_score_band_id' => $band->id,
                'assessment_type' => $questionnaire->type,
                'assessment_status' => 'completed',
                'total_score' => $scored['total_score'],
                'stress_level' => $band->label,
                'started_at' => $completedAt,
                'completed_at' => $completedAt,
            ] + $this->wellbeingColumns($scored, $breakdown));

            foreach ($scored['responses'] as $questionId => $response) {
                $question = $response['question'];
                $option = $response['option'];

                // Scoring validated these rows in the same transaction; this is a defensive guard.
                if (! $question || ! $option) {
                    throw ValidationException::withMessages(['answers' => 'Questionnaire configuration changed during submission. Please retry.']);
                }

                $assessment->responses()->create([
                    'stress_question_id' => $question->id,
                    'question_option_id' => $option->id,
                    'score' => $option->score,
                    'scored_value' => $response['scored_value'] ?? $option->score,
                    'question_text_snapshot' => $question->question_text,
                    'option_text_snapshot' => $option->label,
                ]);
            }

            if ($breakdown !== null) {
                foreach ($breakdown['categories'] as $category) {
                    $assessment->categoryResults()->create([
                        'questionnaire_section_id' => $category['section_id'],
                        'section_title_snapshot' => $category['title'],
                        'raw_score' => $category['raw_score'],
                        'min_possible_score' => $category['min_possible_score'],
                        'max_possible_score' => $category['max_possible_score'],
                        'percentage' => $category['percentage'],
                        'category_weight' => $category['category_weight'],
                        'weighted_score' => $category['weighted_score'],
                    ]);
                }
            }

            return ['assessment' => $assessment, 'score_band' => $band, 'scored' => $scored];
        });
    }

    /**
     * The dynamic-engine columns, only populated when the questionnaire had
     * sections configured. Keeps the flat submission path writing exactly
     * what it always has.
     *
     * @param  array<string, mixed>  $scored
     * @param  array<string, mixed>|null  $breakdown
     * @return array<string, mixed>
     */
    private function wellbeingColumns(array $scored, ?array $breakdown): array
    {
        if ($breakdown === null) {
            return [];
        }

        $stress = $scored['stress_result'] ?? null;

        return [
            'overall_raw_score' => $scored['overall']['raw'] ?? null,
            'overall_weighted_score' => $scored['overall']['weighted'] ?? null,
            'overall_max_weighted_score' => $scored['overall']['max_weighted'] ?? null,
            'overall_percentage' => $scored['overall']['percentage'] ?? null,
            'wellbeing_result_band_id' => $scored['wellbeing_band']?->id,
            'stress_score' => $stress['score'] ?? null,
            'stress_percentage' => $stress['score'] ?? null,
            'stress_result_band_id' => $scored['stress_band']?->id,
            'config_snapshot' => $scored['config_snapshot'] ?? null,
        ];
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
