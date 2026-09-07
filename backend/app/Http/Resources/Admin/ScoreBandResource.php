<?php

namespace App\Http\Resources\Admin;

use App\Models\StressScoreBand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StressScoreBand */
class ScoreBandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scope' => $this->scope,
            'code' => $this->code,
            'label' => $this->label,
            'description' => $this->description,
            'harmony_message' => $this->harmony_message,
            'min_score' => $this->min_score,
            'max_score' => $this->max_score,
            'position' => $this->position,
            'is_active' => $this->is_active,
        ];
    }
}
