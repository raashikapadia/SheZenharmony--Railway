<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the student diary so it survives signing out and reinstalling.
 *
 * The written content is stored through Laravel's `encrypted` cast, following
 * the same approach as `user_mfa_methods`. That is why `title` and `body` are
 * text columns holding ciphertext rather than sized strings, and why nothing
 * here is indexed on content — the database never sees the plain text and
 * cannot search it.
 *
 * Diaries hang off `student_identities`, not `users`, so the diary stays on the
 * pseudonymous side of the identity boundary like every other wellbeing record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_identity_id')
                ->constrained()
                ->cascadeOnDelete();

            // The id the device minted for this diary. Sync matches on it, so a
            // reinstalled app re-attaches to existing rows instead of
            // duplicating them.
            $table->string('client_id', 64);

            $table->text('title');
            $table->unsignedSmallInteger('cover_index')->default(0);

            // The diary's PIN, if it has one: the same salted hash the device
            // stores, never the PIN itself. Kept here so a reinstalled app
            // restores the lock along with the writing.
            $table->string('lock_salt')->nullable();
            $table->string('lock_hash')->nullable();

            // When the device last changed this diary. Distinct from
            // `updated_at`, which records when the server row was written; the
            // client clock is what newest-wins merging compares.
            $table->timestamp('client_created_at');
            $table->timestamp('client_updated_at');

            $table->timestamps();

            // Deletions are kept as tombstones so that deleting a diary on one
            // device removes it from the others instead of being resurrected by
            // the next sync from a device that still has it.
            $table->softDeletes();

            $table->unique(['student_identity_id', 'client_id']);
        });

        Schema::create('diary_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('diary_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 64);

            $table->text('title');
            $table->longText('body');

            $table->timestamp('client_created_at');
            $table->timestamp('client_updated_at');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['diary_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_pages');
        Schema::dropIfExists('diaries');
    }
};
