<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StressScoreBand */
class ScoreBandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'min_score' => $this->min_score,
            'max_score' => $this->max_score,
            'position' => $this->position,
            'is_active' => $this->is_active,
        ];
    }
}
