<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quizzes')) {
            Schema::create('quizzes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('category', 100);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('inactive')->index();
                $table->timestamps();
            });
        }

        Schema::create('managed_quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->string('option_a');
            $table->string('option_b');
            $table->string('option_c');
            $table->string('option_d');
            $table->string('correct_option', 1);
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_identity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();
            $table->index(['quiz_id', 'completed_at']);
            $table->index(['student_identity_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('managed_quiz_questions');
    }
};
