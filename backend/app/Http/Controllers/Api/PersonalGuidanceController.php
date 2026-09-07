<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalGuidance;
use App\Models\StudentIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Student-facing Personal Guidance — a small piece of encouragement for the
 * Home Page. All content is admin-managed; this controller only ever exposes
 * published rows inside their schedule window.
 */
class PersonalGuidanceController extends Controller
{
    /**
     * The item for today. Deterministic per calendar day so it does not
     * flicker between rebuilds, but varies day to day across the whole pool.
     */
    public function current(Request $request): JsonResponse
    {
        $identity = $this->identity($request);
        $pool = PersonalGuidance::query()->visible()->orderBy('id')->get();

        if ($pool->isEmpty()) {
            return response()->json(['data' => null]);
        }

        mt_srand((int) now()->format('Ymd'));
        $item = $pool[mt_rand(0, $pool->count() - 1)];
        mt_srand();

        return response()->json(['data' => $this->present($item, $identity)]);
    }

    /**
     * A different item, chosen at random, optionally excluding the one the
     * student is already looking at.
     */
    public function another(Request $request): JsonResponse
    {
        $identity = $this->identity($request);
        $exclude = (int) $request->query('exclude', 0);

        $query = PersonalGuidance::query()->visible();
        $item = (clone $query)->when($exclude > 0, fn ($q) => $q->whereKeyNot($exclude))
            ->inRandomOrder()->first()
            ?? $query->inRandomOrder()->first();

        if ($item === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->present($item, $identity)]);
    }

    /** The current student's saved guidance, most recently saved first. */
    public function favourites(Request $request): JsonResponse
    {
        $identity = $this->identity($request);
        $favouriteIds = $this->favouriteIds($identity);

        $items = $identity->favouriteGuidance()
            ->visible()
            ->orderByDesc('personal_guidance_favourites.created_at')
            ->get()
            ->map(fn (PersonalGuidance $g) => $this->present($g, $identity, $favouriteIds))
            ->values();

        return response()->json(['data' => $items]);
    }

    public function favourite(Request $request, PersonalGuidance $guidance): JsonResponse
    {
        abort_unless($guidance->isVisible(), 404);

        $identity = $this->identity($request);
        $identity->favouriteGuidance()->syncWithoutDetaching([$guidance->id]);

        return response()->json([
            'data' => $this->present($guidance, $identity, collect([$guidance->id])),
        ], 201);
    }

    public function unfavourite(Request $request, PersonalGuidance $guidance): Response
    {
        $this->identity($request)->favouriteGuidance()->detach($guidance->id);

        return response()->noContent();
    }

    private function identity(Request $request): StudentIdentity
    {
        return $request->user()->studentIdentity()->firstOrFail();
    }

    /** @return Collection<int, int> */
    private function favouriteIds(StudentIdentity $identity): Collection
    {
        return $identity->favouriteGuidance()->pluck('personal_guidance.id');
    }

    /**
     * @param  Collection<int, int>|null  $favouriteIds  reuse a preloaded set to avoid a query per item
     * @return array<string, mixed>
     */
    private function present(PersonalGuidance $guidance, StudentIdentity $identity, ?Collection $favouriteIds = null): array
    {
        $favouriteIds ??= $this->favouriteIds($identity);

        return [
            'id' => $guidance->id,
            'type' => $guidance->type,
            'content' => $guidance->content,
            'author' => $guidance->attribution(),
            'category' => $guidance->category,
            'is_favourite' => $favouriteIds->contains($guidance->id),
        ];
    }
}
