<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_guidance', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);            // affirmation | quote | guidance
            $table->text('content');
            $table->string('author')->nullable();  // quote attribution, or optional admin signature
            $table->string('category', 100)->nullable();
            $table->string('status', 20)->default('draft'); // draft | published | unpublished
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('type');
            $table->index(['status', 'publish_at', 'expires_at'], 'personal_guidance_visibility_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_guidance');
    }
};
