<?php

namespace App\Services;

use App\Models\Diary;
use App\Models\DiaryPage;
use App\Models\StudentIdentity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles a device's copy of the diary with the stored one.
 *
 * The rule is newest-wins per record, compared on the client's own timestamp.
 * Two devices editing different pages therefore both keep their work; two
 * devices editing the same page keep whichever was written last. Deleting is a
 * change like any other, recorded as a tombstone so that a delete on one phone
 * is not undone by the next sync from a phone that still has the diary.
 *
 * Merging on the client's clock rather than the server's is deliberate: a page
 * written offline on Monday and uploaded on Friday must not beat an edit made
 * on another device on Wednesday.
 */
class DiarySyncService
{
    /**
     * Applies [$diaries] from a device, then returns the merged diary.
     *
     * @param  array<int, array<string, mixed>>  $diaries
     * @param  array<string, mixed>|null  $lock  the student's single PIN, if the
     *                                           device has one to offer
     * @return Collection<int, Diary>
     */
    public function sync(StudentIdentity $identity, array $diaries, ?array $lock = null): Collection
    {
        DB::transaction(function () use ($identity, $diaries, $lock): void {
            $this->mergeLock($identity, $lock);
            foreach ($diaries as $incoming) {
                $this->mergeDiary($identity, $incoming);
            }
        });

        return $this->pull($identity);
    }

    /**
     * Newest-wins on the student's PIN, the same as on a diary.
     *
     * A null [$lock] means "this device has no PIN", which is not the same as
     * "there is no PIN": a device that has simply not synced yet must never
     * erase a PIN set on another one. Clearing it is therefore only ever driven
     * by a newer explicit `cleared` instruction.
     *
     * @param  array<string, mixed>|null  $lock
     */
    private function mergeLock(StudentIdentity $identity, ?array $lock): void
    {
        if ($lock === null) {
            return;
        }

        $updatedAt = Carbon::parse($lock['updated_at']);
        $existing = $identity->diaryLock;

        if ($existing !== null && ! $updatedAt->greaterThan($existing->client_updated_at)) {
            return;
        }

        if ($lock['cleared'] ?? false) {
            $existing?->delete();

            return;
        }

        $identity->diaryLock()->updateOrCreate([], [
            'salt' => $lock['salt'],
            'hash' => $lock['hash'],
            'client_updated_at' => $updatedAt,
        ]);
    }

    /**
     * Everything the student has, tombstones excluded.
     *
     * @return Collection<int, Diary>
     */
    public function pull(StudentIdentity $identity): Collection
    {
        return $identity->diaries()
            ->with(['pages' => fn ($query) => $query->orderByDesc('client_updated_at')])
            ->orderByDesc('client_updated_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function mergeDiary(StudentIdentity $identity, array $incoming): void
    {
        $updatedAt = Carbon::parse($incoming['updated_at']);

        // withTrashed, so a diary the student already deleted elsewhere is
        // compared against its tombstone rather than silently recreated.
        $diary = $identity->diaries()->withTrashed()
            ->where('client_id', $incoming['client_id'])
            ->first();

        if ($diary === null) {
            if ($incoming['deleted'] ?? false) {
                // Never seen here and already gone there — nothing to record.
                return;
            }
            $diary = $identity->diaries()->create($this->diaryAttributes($incoming, $updatedAt));
        } elseif ($updatedAt->greaterThan($diary->client_updated_at)) {
            $diary->fill($this->diaryAttributes($incoming, $updatedAt));
            $diary->deleted_at = ($incoming['deleted'] ?? false) ? $updatedAt : null;
            $diary->save();
        }

        // Pages are merged even when the diary itself is older, because a new
        // page does not have to change anything about the diary that holds it.
        foreach ($incoming['pages'] ?? [] as $page) {
            $this->mergePage($diary, $page);
        }
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function mergePage(Diary $diary, array $incoming): void
    {
        $updatedAt = Carbon::parse($incoming['updated_at']);

        $page = $diary->pages()->withTrashed()
            ->where('client_id', $incoming['client_id'])
            ->first();

        if ($page === null) {
            if ($incoming['deleted'] ?? false) {
                return;
            }
            $diary->pages()->create($this->pageAttributes($incoming, $updatedAt));

            return;
        }

        if (! $updatedAt->greaterThan($page->client_updated_at)) {
            return;
        }

        $page->fill($this->pageAttributes($incoming, $updatedAt));
        $page->deleted_at = ($incoming['deleted'] ?? false) ? $updatedAt : null;
        $page->save();
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function diaryAttributes(array $incoming, Carbon $updatedAt): array
    {
        return [
            'client_id' => $incoming['client_id'],
            'title' => $incoming['title'] ?? '',
            'cover_index' => $incoming['cover_index'] ?? 0,
            'is_locked' => $incoming['is_locked'] ?? false,
            'client_created_at' => Carbon::parse($incoming['created_at'] ?? $updatedAt),
            'client_updated_at' => $updatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function pageAttributes(array $incoming, Carbon $updatedAt): array
    {
        return [
            'client_id' => $incoming['client_id'],
            'title' => $incoming['title'] ?? '',
            'body' => $incoming['body'] ?? '',
            'client_created_at' => Carbon::parse($incoming['created_at'] ?? $updatedAt),
            'client_updated_at' => $updatedAt,
        ];
    }

    /**
     * The student's single PIN, as the device needs it back.
     *
     * @return array<string, mixed>|null
     */
    public function lockPayload(StudentIdentity $identity): ?array
    {
        $lock = $identity->diaryLock()->first();
        if ($lock === null) {
            return null;
        }

        return [
            'salt' => $lock->salt,
            'hash' => $lock->hash,
            'updated_at' => $lock->client_updated_at?->toIso8601String(),
        ];
    }

    /**
     * Shapes a diary the way the Flutter models read it back.
     *
     * @return array<string, mixed>
     */
    public function payload(Diary $diary): array
    {
        return [
            'client_id' => $diary->client_id,
            'title' => $diary->title,
            'cover_index' => $diary->cover_index,
            'is_locked' => $diary->is_locked,
            'created_at' => $diary->client_created_at?->toIso8601String(),
            'updated_at' => $diary->client_updated_at?->toIso8601String(),
            'pages' => $diary->pages->map(
                fn (DiaryPage $page): array => [
                    'client_id' => $page->client_id,
                    'title' => $page->title,
                    'body' => $page->body,
                    'created_at' => $page->client_created_at?->toIso8601String(),
                    'updated_at' => $page->client_updated_at?->toIso8601String(),
                ],
            )->all(),
        ];
    }
}
