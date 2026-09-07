<?php

namespace App\Http\Resources\Admin;

use App\Models\Questionnaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Questionnaire */
class QuestionnaireResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'period' => $this->period,
            'version' => $this->version,
            'status' => $this->status,
            'is_active' => $this->is_active,
            'question_count' => $this->whenCounted('questions'),
            'sections' => $this->whenLoaded('sections', fn () => $this->sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'position' => $section->position,
                'category_weight' => (float) $section->category_weight,
                'is_active' => (bool) $section->is_active,
            ])->values()),
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
            'score_bands' => ScoreBandResource::collection($this->whenLoaded('scoreBands')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
