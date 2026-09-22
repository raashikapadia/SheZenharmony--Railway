<?php

namespace App\Services;

use App\Models\Questionnaire;
use App\Models\StressQuestion;

/**
 * The single place a questionnaire is turned into student-facing JSON.
 *
 * The list, the detail endpoint and the registration endpoint all render
 * through here so the shape the app parses can never drift between them.
 * Nothing about a particular instrument is assumed: question types, option
 * sets, sections and result scale are all read from stored configuration.
 */
class QuestionnairePresenter
{
    /**
     * A row in the "questionnaires you can sit" list — enough to decide
     * whether to open it, without shipping every question.
     *
     * @param  array{attempts: int, last_completed_at: string|null}|null  $attempt
     * @return array<string, mixed>
     */
    public function summary(Questionnaire $questionnaire, ?array $attempt = null): array
    {
        return [
            'id' => $questionnaire->id,
            'title' => $questionnaire->title,
            'description' => $questionnaire->description,
            'period' => $questionnaire->period,
            'type' => $questionnaire->type,
            'purpose' => $questionnaire->purpose,
            'version' => $questionnaire->version,
            'estimated_minutes' => $questionnaire->estimated_minutes,
            'question_count' => (int) ($questionnaire->questions_count ?? 0),
            'section_count' => (int) ($questionnaire->sections_count ?? 0),
            'attempt_count' => $attempt['attempts'] ?? 0,
            'last_completed_at' => isset($attempt['last_completed_at'])
                ? $this->iso($attempt['last_completed_at'])
                : null,
        ];
    }

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
            'purpose' => $questionnaire->purpose,
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

    /** Accepts the raw string an aggregate query returns, or a Carbon instance. */
    private function iso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return \Illuminate\Support\Carbon::parse((string) $value)->toIso8601String();
    }
}
