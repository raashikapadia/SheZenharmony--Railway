<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\StressQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Clones a questionnaire — including independent copies of its sections,
 * questions, options and score bands — into a new draft version. The clone
 * is fully independent: editing it never affects the source version or any
 * assessment already recorded against it. Shared by the Blade admin and the
 * JSON admin API so the two never diverge.
 */
class QuestionnaireVersioner
{
    public function draftFrom(Questionnaire $source, ?int $creatorId = null): Questionnaire
    {
        $source->loadMissing(['sections', 'questions.options', 'scoreBands']);

        return DB::transaction(function () use ($source, $creatorId): Questionnaire {
            $nextVersion = (int) Questionnaire::query()->where('type', $source->type)->max('version') + 1;

            $clone = Questionnaire::query()->create([
                'title' => $source->title,
                'description' => $source->description,
                'period' => $source->period,
                'type' => $source->type,
                'version' => $nextVersion,
                'status' => 'draft',
                'is_active' => false,
                'created_by_user_id' => $creatorId,
            ]);

            // Independent copies of the sections, mapped old id => new id so
            // question placement survives the clone.
            $sectionMap = [];
            foreach ($source->sections as $section) {
                $sectionMap[$section->id] = $clone->sections()->create([
                    'title' => $section->title,
                    'description' => $section->description,
                    'position' => $section->position,
                    'category_weight' => $section->category_weight,
                    'is_active' => $section->is_active,
                ])->id;
            }

            foreach ($source->questions as $question) {
                $newQuestion = StressQuestion::query()->create([
                    'question_text' => $question->question_text,
                    'dimension' => $question->dimension,
                    'help_text' => $question->help_text,
                    'question_type' => $question->question_type,
                    'min_score' => $question->min_score,
                    'max_score' => $question->max_score,
                    'wellbeing_weight' => $question->wellbeing_weight,
                    'is_reverse_scored' => $question->is_reverse_scored,
                    'stress_relevant' => $question->stress_relevant,
                    'stress_direction' => $question->stress_direction,
                    'stress_weight' => $question->stress_weight,
                    'position' => $question->position,
                    'is_active' => true,
                    'is_sensitive' => $question->is_sensitive,
                    'created_by_user_id' => $creatorId,
                ]);

                foreach ($question->options as $option) {
                    $newQuestion->options()->create([
                        'label' => $option->label,
                        'value' => $option->value,
                        'score' => $option->score,
                        'position' => $option->position,
                        'is_active' => true,
                    ]);
                }

                $clone->questions()->attach($newQuestion->id, [
                    'position' => $question->pivot->position,
                    'is_required' => $question->pivot->is_required,
                    'questionnaire_section_id' => $sectionMap[$question->pivot->questionnaire_section_id] ?? null,
                ]);
            }

            foreach ($source->scoreBands as $band) {
                $clone->scoreBands()->create([
                    'scope' => $band->scope,
                    'code' => $band->code,
                    'label' => $band->label,
                    'description' => $band->description,
                    'harmony_message' => $band->harmony_message,
                    'min_score' => $band->min_score,
                    'max_score' => $band->max_score,
                    'position' => $band->position,
                    'is_active' => $band->is_active,
                    'created_by_user_id' => $creatorId,
                ]);
            }

            return $clone;
        });
    }
}
