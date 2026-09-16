<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncDiaryRequest;
use App\Models\Diary;
use App\Services\DiarySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The student's own diary.
 *
 * Every action reads and writes through the caller's student identity, so
 * there is no route here that can return someone else's diary. There is
 * deliberately no admin counterpart to this controller.
 */
class DiaryController extends Controller
{
    public function __construct(private readonly DiarySyncService $sync) {}

    /**
     * Everything stored for this student — what a freshly installed app asks
     * for before it has anything of its own to push.
     */
    public function index(Request $request): JsonResponse
    {
        $identity = $this->identity($request);

        return response()->json([
            'data' => $this->sync->pull($identity)
                ->map(fn (Diary $diary): array => $this->sync->payload($diary)),
            // The one PIN this student uses for every diary they lock.
            'lock' => $this->sync->lockPayload($identity),
        ]);
    }

    /**
     * Merges the device's copy with the stored one and returns the result, so
     * one round trip both uploads local work and brings down anything written
     * on another device.
     */
    public function sync(SyncDiaryRequest $request): JsonResponse
    {
        $identity = $this->identity($request);
        $diaries = $this->sync->sync(
            $identity,
            $request->validated('diaries'),
            $request->validated('lock'),
        );

        return response()->json([
            'data' => $diaries->map(fn (Diary $diary): array => $this->sync->payload($diary)),
            'lock' => $this->sync->lockPayload($identity->refresh()),
        ]);
    }

    /**
     * Matches how the other student-owned resources resolve their identity, so
     * a student who has not been given one yet still gets a working diary.
     */
    private function identity(Request $request)
    {
        return $request->user()->studentIdentity()->firstOrCreate([], [
            'pseudonymous_uuid' => $request->user()->pseudonymous_uuid,
        ]);
    }
}
