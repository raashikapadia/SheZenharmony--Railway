<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the diary PIN from each diary to one per student.
 *
 * A student now chooses a PIN once, the first time they lock anything, and
 * every diary they lock afterwards opens with that same PIN. A diary therefore
 * only records *whether* it is locked; the PIN itself lives in `diary_locks`,
 * one row per student.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_locks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_identity_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // The same salted hash the device holds. The PIN itself is never
            // sent here and never stored.
            $table->string('salt');
            $table->string('hash');

            $table->timestamp('client_updated_at');
            $table->timestamps();
        });

        Schema::table('diaries', function (Blueprint $table): void {
            $table->boolean('is_locked')->default(false)->after('cover_index');
        });

        $this->carryExistingLocksOver();

        Schema::table('diaries', function (Blueprint $table): void {
            $table->dropColumn(['lock_salt', 'lock_hash']);
        });
    }

    /**
     * Keeps diaries that were already locked locked.
     *
     * Where a student had locked several diaries with different PINs, the most
     * recently set one becomes their single PIN — there is no way to keep two,
     * and silently unlocking the others would be the worse outcome.
     */
    private function carryExistingLocksOver(): void
    {
        DB::table('diaries')->whereNotNull('lock_hash')->update(['is_locked' => true]);

        $newestLockPerStudent = DB::table('diaries')
            ->whereNotNull('lock_hash')
            ->whereNull('deleted_at')
            ->orderBy('client_updated_at')
            ->get(['student_identity_id', 'lock_salt', 'lock_hash', 'client_updated_at']);

        $locks = [];
        foreach ($newestLockPerStudent as $diary) {
            // Ordered oldest first, so the last write per student wins.
            $locks[$diary->student_identity_id] = [
                'student_identity_id' => $diary->student_identity_id,
                'salt' => $diary->lock_salt,
                'hash' => $diary->lock_hash,
                'client_updated_at' => $diary->client_updated_at,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($locks !== []) {
            DB::table('diary_locks')->insert(array_values($locks));
        }
    }

    public function down(): void
    {
        Schema::table('diaries', function (Blueprint $table): void {
            $table->string('lock_salt')->nullable()->after('cover_index');
            $table->string('lock_hash')->nullable()->after('lock_salt');
        });

        // Puts each student's single PIN back on every diary that was locked.
        foreach (DB::table('diary_locks')->get() as $lock) {
            DB::table('diaries')
                ->where('student_identity_id', $lock->student_identity_id)
                ->where('is_locked', true)
                ->update(['lock_salt' => $lock->salt, 'lock_hash' => $lock->hash]);
        }

        Schema::table('diaries', function (Blueprint $table): void {
            $table->dropColumn('is_locked');
        });

        Schema::dropIfExists('diary_locks');
    }
};
