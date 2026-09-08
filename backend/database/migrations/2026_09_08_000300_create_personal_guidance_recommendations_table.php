<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rules that match a piece of guidance to an assessment outcome. Deliberately
 * mirrors `intervention_recommendations` so both content types are matched the
 * same way: a rule may target a stress band, a questionnaire section (the
 * "need"), or both. Guidance with no active rules applies to everyone, which
 * keeps all existing guidance visible without any admin action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_guidance_recommendations', function (Blueprint $table): void {
            $table->id();
            // Constraint names are set explicitly: the generated names exceed
            // MySQL's 64-character identifier limit for this table name.
            $table->foreignId('personal_guidance_id')
                ->constrained('personal_guidance', indexName: 'pgr_guidance_foreign')
                ->cascadeOnDelete();
            $table->foreignId('stress_score_band_id')
                ->nullable()
                ->constrained('stress_score_bands', indexName: 'pgr_band_foreign')
                ->cascadeOnDelete();
            $table->foreignId('questionnaire_section_id')
                ->nullable()
                ->constrained('questionnaire_sections', indexName: 'pgr_section_foreign')
                ->cascadeOnDelete();
            $table->string('result_level', 50)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['stress_score_band_id', 'is_active'], 'pgr_band_active_index');
            $table->index(['questionnaire_section_id', 'is_active'], 'pgr_section_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_guidance_recommendations');
    }
};
