<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WellbeingActivity;
use Illuminate\Http\JsonResponse;

class WellbeingActivityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => WellbeingActivity::query()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('title')
                ->get()
                ->map(fn (WellbeingActivity $activity): array => [
                    'title' => $activity->title,
                    'description' => $activity->description,
                    'category' => $activity->category,
                    'video_url' => $activity->video_url,
                    'video_type' => $activity->video_type,
                ])->values(),
        ]);
    }
}
