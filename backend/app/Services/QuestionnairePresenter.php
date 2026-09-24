<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\StressQuestion;

/**
 * The single place a questionnaire is turned into student-facing JSON.
 *
 * Nothing about a particular instrument is assumed: question types, option
 * sets, answer modes, sections and the result scale are all read from stored
 * configuration, so the app renders whatever the admin built.
 */
class QuestionnairePresenter
{
    /**
     * The full questionnaire, ready to answer. Sections and questions come
     * pre-filtered to the active rows by the caller's eager loads.
     *
     * @return array<string, mixed>
     */
    public function forTaking(Questionnaire $questionnaire): array
    {
        return [
            'id' => $questionnaire->id,
            'title' => $questionnaire->title,
            'description' => $questionnaire->description,
            'period' => $questionnaire->period,
            'type' => $questionnaire->type,
            'version' => $questionnaire->version,
            'estimated_minutes' => $questionnaire->estimated_minutes,
            'sections' => $questionnaire->sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'position' => $section->position,
            ])->values(),
            'questions' => $questionnaire->questions
                ->map(fn (StressQuestion $question) => $this->question($question))
                ->values(),
        ];
    }

    /**
     * One question and its answer options. `type` is passed through verbatim
     * so the client renders whatever the admin configured rather than
     * assuming a rating scale.
     *
     * @return array<string, mixed>
     */
    private function question(StressQuestion $question): array
    {
        return [
            'id' => $question->id,
            'text' => $question->question_text,
            'help_text' => $question->help_text,
            'required' => (bool) $question->pivot->is_required,
            'type' => $question->question_type,
            // How many options may be chosen — the client renders radio
            // buttons or checkboxes from this, never from the type alone.
            'answer_mode' => $question->answerMode(),
            'max_selections' => $question->allowsMultipleAnswers() ? $question->selectionLimit() : 1,
            'position' => $question->pivot->position,
            'section_id' => $question->pivot->questionnaire_section_id,
            'options' => $question->options->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
                'value' => $option->value,
            ])->values(),
        ];
    }
}
