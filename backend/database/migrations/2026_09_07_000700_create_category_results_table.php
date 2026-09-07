<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-attempt, per-section wellbeing breakdown. `questionnaire_section_id`
 * is nullable with nullOnDelete and the title is snapshotted, so a
 * historical breakdown survives a section being archived or a new
 * questionnaire version being published.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stress_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('questionnaire_section_id')->nullable()
                ->constrained('questionnaire_sections')->nullOnDelete();
            $table->string('section_title_snapshot');
            $table->decimal('raw_score', 10, 2);
            $table->decimal('min_possible_score', 10, 2);
            $table->decimal('max_possible_score', 10, 2);
            $table->decimal('percentage', 6, 2);
            $table->decimal('category_weight', 6, 2);
            $table->decimal('weighted_score', 10, 2);
            $table->timestamps();
            $table->index('stress_assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_results');
    }
};
