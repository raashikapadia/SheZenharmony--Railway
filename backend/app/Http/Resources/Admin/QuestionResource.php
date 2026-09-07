<?php

namespace App\Http\Resources\Admin;

use App\Models\StressQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StressQuestion */
class QuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'dimension' => $this->dimension,
            'help_text' => $this->help_text,
            'question_type' => $this->question_type,
            'is_active' => $this->is_active,
            'is_sensitive' => $this->is_sensitive,
            'min_score' => $this->min_score,
            'max_score' => $this->max_score,
            'wellbeing_weight' => (float) $this->wellbeing_weight,
            'is_reverse_scored' => (bool) $this->is_reverse_scored,
            'stress_relevant' => (bool) $this->stress_relevant,
            'stress_direction' => $this->stress_direction,
            'stress_weight' => (float) $this->stress_weight,
            'position' => $this->whenPivotLoaded('questionnaire_questions', fn () => $this->pivot->position, $this->position),
            'is_required' => $this->whenPivotLoaded('questionnaire_questions', fn () => (bool) $this->pivot->is_required, $this->is_required),
            'questionnaire_section_id' => $this->whenPivotLoaded('questionnaire_questions', fn () => $this->pivot->questionnaire_section_id, null),
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
