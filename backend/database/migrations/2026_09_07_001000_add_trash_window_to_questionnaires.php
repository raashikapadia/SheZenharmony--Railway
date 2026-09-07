<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recoverable "trash" window for questionnaires. Deleting a questionnaire
 * that has no assessment history marks `trashed_at` and sets `purge_after`
 * a few days out; a scheduled command hard-deletes it once that passes,
 * unless an admin restores it first. Plain nullable columns — no soft-delete
 * global scope, so version numbering and every existing query are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->timestamp('trashed_at')->nullable()->after('published_at');
            $table->timestamp('purge_after')->nullable()->after('trashed_at');
            $table->index('purge_after');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->dropIndex(['purge_after']);
            $table->dropColumn(['trashed_at', 'purge_after']);
        });
    }
};
