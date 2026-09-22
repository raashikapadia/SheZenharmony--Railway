<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\StressQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Clones a questionnaire — including independent copies of its sections,
 * questions, options, result levels and the support recommended for each —
 * into a new draft version. The clone is fully independent: editing it never
 * affects the source version or any assessment already recorded against it.
 * Shared by the Blade admin and the JSON admin API so the two never diverge.
 *
 * `draftFrom()` continues the source's version family (the next version of
 * the same questionnaire); `duplicate()` starts a brand-new family so the
 * copy can be renamed, reworked and published alongside the original.
 */
class QuestionnaireVersioner
{
    public function draftFrom(Questionnaire $source, ?int $creatorId = null): Questionnaire
    {
        return $this->clone($source, $creatorId, sameFamily: true);
    }

    /**
     * An independent copy in its own version family, titled "… (copy)" so it
     * is never mistaken for the original in the questionnaire list.
     */
    public function duplicate(Questionnaire $source, ?int $creatorId = null): Questionnaire
    {
        return $this->clone($source, $creatorId, sameFamily: false);
    }

    private function clone(Questionnaire $source, ?int $creatorId, bool $sameFamily): Questionnaire
    {
        $source->loadMissing([
            'sections',
            'questions.options',
            'scoreBands.recommendations',
        ]);

        return DB::transaction(function () use ($source, $creatorId, $sameFamily): Questionnaire {
            if ($sameFamily) {
                $type = $source->type;
                $title = $source->title;
                $purpose = $source->purpose ?? Questionnaire::PURPOSE_LIBRARY;
            } else {
                $title = $source->title.' (copy)';
                $type = Questionnaire::deriveType($title);
                // A copy is always a library questionnaire: there is only ever
                // one registration baseline family.
                $purpose = Questionnaire::PURPOSE_LIBRARY;
            }
            $nextVersion = (int) Questionnaire::query()->where('type', $type)->max('version') + 1;

            $clone = Questionnaire::query()->create([
                'title' => $title,
                'description' => $source->description,
                'period' => $source->period,
                'type' => $type,
                'purpose' => $purpose,
                'version' => $nextVersion,
                'result_scale_min' => $source->result_scale_min,
                'result_scale_max' => $source->result_scale_max,
                'scoring_method' => $source->scoringMethod(),
                'section_weighting' => $source->usesEqualSectionWeights() ? Questionnaire::WEIGHTING_EQUAL : Questionnaire::WEIGHTING_CUSTOM,
                'estimated_minutes' => $source->estimated_minutes,
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
                $newQuestion = $this->cloneQuestion($question, $creatorId);

                $clone->questions()->attach($newQuestion->id, [
                    'position' => $question->pivot->position,
                    'is_required' => $question->pivot->is_required,
                    'questionnaire_section_id' => $sectionMap[$question->pivot->questionnaire_section_id] ?? null,
                ]);
            }

            foreach ($source->scoreBands as $band) {
                $newBand = $clone->scoreBands()->create([
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

                // The support recommended for each level travels with it.
                foreach ($band->recommendations as $link) {
                    $newBand->recommendations()->create([
                        'intervention_id' => $link->intervention_id,
                        'questionnaire_section_id' => $link->questionnaire_section_id
                            ? ($sectionMap[$link->questionnaire_section_id] ?? null)
                            : null,
                        'result_level' => $link->result_level,
                        'priority' => $link->priority,
                        'is_active' => $link->is_active,
                    ]);
                }
            }

            return $clone;
        });
    }

    /**
     * A fresh question row carrying every setting of the source, with fresh
     * option rows. Shared with section duplication so a copied section's
     * questions are as complete as a copied questionnaire's.
     */
    public function cloneQuestion(StressQuestion $question, ?int $creatorId = null): StressQuestion
    {
        $question->loadMissing('options');

        $newQuestion = StressQuestion::query()->create([
            'question_text' => $question->question_text,
            'dimension' => $question->dimension,
            'help_text' => $question->help_text,
            'question_type' => $question->question_type,
            'answer_mode' => $question->answerMode(),
            'max_selections' => $question->max_selections,
            'scoring_method' => $question->scoring_method ?? StressQuestion::SCORING_DIRECT,
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

        foreach ($question->options->where('is_active', true) as $option) {
            $newQuestion->options()->create([
                'label' => $option->label,
                'value' => $option->value,
                'score' => $option->score,
                'position' => $option->position,
                'is_active' => true,
            ]);
        }

        return $newQuestion;
    }
}
