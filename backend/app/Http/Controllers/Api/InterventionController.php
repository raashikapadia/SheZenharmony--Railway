<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
use App\Models\InterventionUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterventionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Intervention::query()
            ->where('is_active', true)
            ->orderBy('title');

        if ($request->filled('stress_level')) {
            $query->where(function ($subQuery) use ($request): void {
                $subQuery
                    ->whereNull('stress_level')
                    ->orWhere('stress_level', $request->string('stress_level')->toString());
            });
        }

        if ($request->filled('content_type')) {
            $types = collect(explode(',', $request->string('content_type')->toString()))
                ->map(fn (string $type): string => trim($type))
                ->filter()
                ->values();

            if ($types->isNotEmpty()) {
                $query->whereIn('content_type', $types);
            }
        }

        return response()->json([
            'data' => $query->get()->map(fn (Intervention $intervention): array => [
                'id' => $intervention->id,
                // Stable identifier the student app dispatches games on, so a
                // renamed game still opens the screen it belongs to.
                'slug' => $intervention->slug,
                'title' => $intervention->title,
                'description' => $intervention->description,
                'content_type' => $intervention->content_type,
                'stress_level' => $intervention->stress_level,
                'external_url' => $intervention->external_url,
                'instructions' => $intervention->instructions,
            ])->values(),
        ]);
    }

    public function play(Request $request, Intervention $intervention): JsonResponse
    {
        abort_unless($intervention->is_active && $intervention->content_type === 'game', 404);

        $identity = $request->user()->studentIdentity()->firstOrCreate([], [
            'pseudonymous_uuid' => $request->user()->pseudonymous_uuid,
        ]);

        InterventionUsage::query()->create([
            'intervention_id' => $intervention->id,
            'student_identity_id' => $identity->id,
            'usage_status' => 'started',
            'started_at' => now(),
        ]);

        return response()->json(['data' => ['recorded' => true]], 201);
    }
}
