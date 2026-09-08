<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The categories Shezen can recognise, and the keywords that select them.
 *
 * Matching is deterministic: keywords are compared against the student's
 * message, and the highest-priority active intent wins. Crisis intents are
 * checked before anything else. Nothing here is generated — an administrator
 * writes every keyword and every category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_intents', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Comma-separated phrases an admin maintains. Stored as text so the
            // admin form stays a single, familiar textarea.
            $table->text('keywords')->nullable();

            // Crisis intents are evaluated first and short-circuit matching.
            $table->boolean('is_crisis')->default(false);

            // Shown as conversation starters when a chat opens.
            $table->boolean('is_starter')->default(false);

            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'is_crisis', 'priority'], 'chat_intents_matching_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_intents');
    }
};
