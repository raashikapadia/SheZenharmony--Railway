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

        return response()->json([
            'data' => $query->get(),
        ]);
    }
}
