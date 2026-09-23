<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_buddy_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 20);
            $table->string('current_key', 20)->nullable()->unique();
            $table->text('welcome_message')->nullable();
            $table->text('fallback_message')->nullable();
            $table->text('safety_message')->nullable();
            $table->json('suggested_topics')->nullable();
            $table->boolean('safety_content_approved')->default(false);
            $table->string('safety_content_hash', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'current_key']);
        });

        Schema::create('chat_buddy_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_buddy_release_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_safety')->default(false);
            $table->text('reply');
            $table->timestamps();
            $table->index(['chat_buddy_release_id', 'is_safety', 'priority']);
        });

        Schema::create('chat_buddy_topic_phrases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_buddy_topic_id')->constrained()->cascadeOnDelete();
            $table->string('phrase', 255);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->index(['chat_buddy_topic_id', 'position']);
        });

        Schema::create('chat_buddy_follow_up_prompts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_buddy_topic_id')->constrained()->cascadeOnDelete();
            $table->string('prompt', 255);
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });

        Schema::create('chat_buddy_topic_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_buddy_topic_id')->constrained()->cascadeOnDelete();
            $table->string('link_type', 40);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('label');
            $table->text('url')->nullable();
            $table->string('target_name')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
            $table->index(['link_type', 'target_id']);
        });

        Schema::create('chat_buddy_publication_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_buddy_release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->timestamps();
        });

        Schema::create('chat_buddy_seed_markers', function (Blueprint $table): void {
            $table->id();
            $table->string('fixture_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_buddy_seed_markers');
        Schema::dropIfExists('chat_buddy_publication_logs');
        Schema::dropIfExists('chat_buddy_topic_links');
        Schema::dropIfExists('chat_buddy_follow_up_prompts');
        Schema::dropIfExists('chat_buddy_topic_phrases');
        Schema::dropIfExists('chat_buddy_topics');
        Schema::dropIfExists('chat_buddy_releases');
    }
};
