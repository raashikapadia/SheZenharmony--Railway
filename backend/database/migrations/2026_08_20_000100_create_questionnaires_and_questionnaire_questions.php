<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaires', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 50)->default('stress');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['type', 'version']);
            $table->index(['type', 'status', 'is_active'], 'idx_questionnaires_availability');
        });

        Schema::create('questionnaire_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stress_question_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(['questionnaire_id', 'stress_question_id'], 'uq_questionnaire_question');
            $table->index(['questionnaire_id', 'position'], 'idx_questionnaire_questions_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_questions');
        Schema::dropIfExists('questionnaires');
    }
};
