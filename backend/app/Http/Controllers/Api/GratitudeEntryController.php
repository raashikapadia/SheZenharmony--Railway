<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GratitudeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GratitudeEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $identity = $request->user()->studentIdentity()->firstOrCreate([], [
            'pseudonymous_uuid' => $request->user()->pseudonymous_uuid,
        ]);

        return response()->json([
            'data' => $identity->gratitudeEntries()->latest()->get()->map(
                fn (GratitudeEntry $entry): array => $this->payload($entry),
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'symbol' => ['required', 'string', 'max:20'],
        ]);
        $identity = $request->user()->studentIdentity()->firstOrCreate([], [
            'pseudonymous_uuid' => $request->user()->pseudonymous_uuid,
        ]);

        $duplicate = $identity->gratitudeEntries()
            ->where('text', $validated['text'])
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'That gratitude moment is already saved.'], 409);
        }

        $entry = $identity->gratitudeEntries()->create($validated);
        return response()->json(['data' => $this->payload($entry)], 201);
    }

    public function destroy(Request $request, GratitudeEntry $gratitudeEntry): JsonResponse
    {
        $identity = $request->user()->studentIdentity()->firstOrCreate([], [
            'pseudonymous_uuid' => $request->user()->pseudonymous_uuid,
        ]);
        abort_unless($gratitudeEntry->student_identity_id === $identity->id, 404);
        $gratitudeEntry->delete();
        return response()->json([], 204);
    }

    private function payload(GratitudeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'text' => $entry->text,
            'symbol' => $entry->symbol,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }
}
