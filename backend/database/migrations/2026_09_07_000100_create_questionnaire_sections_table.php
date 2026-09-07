<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-class questionnaire sections (a.k.a. wellbeing categories). Replaces
 * the loose free-text `stress_questions.dimension` grouping with an ordered,
 * weighted entity scoped to one questionnaire version. Purely additive: the
 * existing section-less questionnaire flow keeps working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            // "How much this category contributes to the overall wellbeing score."
            $table->decimal('category_weight', 6, 2)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['questionnaire_id', 'position'], 'idx_questionnaire_sections_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_sections');
    }
};
