<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What Shezen says for a given intent, and the quick replies it offers next.
 *
 * A response belongs to one intent. Quick replies are stored as rows in
 * `chat_quick_replies` so an administrator can point each button at another
 * intent or at an existing area of the app, which is what keeps the
 * conversation moving without any generated text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_intent_id')
                ->constrained('chat_intents')
                ->cascadeOnDelete();
            $table->text('message');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['chat_intent_id', 'is_active'], 'chat_responses_intent_index');
        });

        Schema::create('chat_quick_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_response_id')
                ->constrained('chat_responses')
                ->cascadeOnDelete();
            $table->string('label');

            // Where tapping this button leads. Either another intent in the
            // tree, or one of the existing app sections.
            $table->foreignId('next_chat_intent_id')
                ->nullable()
                ->constrained('chat_intents')
                ->nullOnDelete();
            $table->string('links_to', 40)->nullable();

            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->index('chat_response_id', 'chat_quick_replies_response_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_quick_replies');
        Schema::dropIfExists('chat_responses');
    }
};
