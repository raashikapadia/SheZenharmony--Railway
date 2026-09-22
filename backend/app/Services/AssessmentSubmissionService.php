<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\QuestionOption;
use App\Models\StressAssessment;
use App\Models\StressScoreBand;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentSubmissionService
{
    public function __construct(private readonly AssessmentScoringService $scoringService) {}

    /**
     * @param  list<array{question_id: int, option_id?: int|null, option_ids?: array<int, int>|null}>  $submittedAnswers
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
                (int) $answer['question_id'] => self::chosenOptions($answer),
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
                /** @var Collection<int, QuestionOption> $options */
                $options = $response['options'];

                // Scoring validated these rows in the same transaction; this is a defensive guard.
                if (! $question || $options->isEmpty()) {
                    throw ValidationException::withMessages(['answers' => 'Questionnaire configuration changed during submission. Please retry.']);
                }

                // A single-answer question keeps its option FK; a multi-select
                // answer lists every ticked option so the transcript is
                // complete and the wording is frozen at submission time.
                $assessment->responses()->create([
                    'stress_question_id' => $question->id,
                    'question_option_id' => $response['option']?->id,
                    'selected_option_ids' => $question->allowsMultipleAnswers()
                        ? $options->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                        : null,
                    'score' => $response['raw'],
                    'scored_value' => $response['scored_value'],
                    'question_text_snapshot' => $question->question_text,
                    'option_text_snapshot' => $options->pluck('label')->join(', '),
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
     * The option id(s) one submitted answer names. `option_ids` wins when
     * both are present; a lone `option_id` stays an int so single-answer
     * questions keep their exact historical shape.
     *
     * @param  array<string, mixed>  $answer
     * @return int|array<int, int>
     */
    public static function chosenOptions(array $answer): int|array
    {
        if (! empty($answer['option_ids']) && is_array($answer['option_ids'])) {
            return array_values(array_map('intval', $answer['option_ids']));
        }

        return (int) ($answer['option_id'] ?? 0);
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
