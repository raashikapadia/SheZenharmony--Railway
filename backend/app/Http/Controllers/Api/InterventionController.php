<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Intervention;
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
                'title' => $intervention->title,
                'description' => $intervention->description,
                'content_type' => $intervention->content_type,
                'stress_level' => $intervention->stress_level,
                'external_url' => $intervention->external_url,
                'instructions' => $intervention->instructions,
            ])->values(),
        ]);
    }
}
