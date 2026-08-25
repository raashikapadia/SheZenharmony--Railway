<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StressQuestion */
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
            'position' => $this->whenPivotLoaded('questionnaire_questions', fn () => $this->pivot->position, $this->position),
            'is_required' => $this->whenPivotLoaded('questionnaire_questions', fn () => (bool) $this->pivot->is_required, $this->is_required),
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
