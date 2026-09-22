<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the scoring engine configuration-driven end to end.
 *
 *  - A questionnaire chooses how its overall result is calculated
 *    (`scoring_method`) and how its sections are weighted
 *    (`section_weighting`). Existing rows keep today's behaviour exactly:
 *    the points total normalised onto the result scale, with the weights
 *    that are already stored on each section.
 *  - A question separates *how it is answered* (`answer_mode`: one option or
 *    several, capped by `max_selections`) from *how the answer becomes
 *    points* (`scoring_method`). Existing questions are single-answer,
 *    scored directly from the chosen option — what they always were.
 *  - A response can record several chosen options, so multi-select answers
 *    keep a complete transcript alongside the single-option FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            // 'points_total' | 'weighted_sections'
            $table->string('scoring_method', 30)->default('points_total')->after('result_scale_max');
            // 'custom' (use each section's stored weight) | 'equal' (share evenly)
            $table->string('section_weighting', 20)->default('custom')->after('scoring_method');
            $table->unsignedSmallInteger('estimated_minutes')->nullable()->after('section_weighting');
        });

        Schema::table('stress_questions', function (Blueprint $table): void {
            // 'single' | 'multiple'
            $table->string('answer_mode', 20)->default('single')->after('question_type');
            $table->unsignedSmallInteger('max_selections')->nullable()->after('answer_mode');
            // 'direct' | 'count_selected' | 'max_selected'
            $table->string('scoring_method', 30)->default('direct')->after('max_selections');
        });

        Schema::table('stress_responses', function (Blueprint $table): void {
            $table->json('selected_option_ids')->nullable()->after('question_option_id');
        });
    }

    public function down(): void
    {
        Schema::table('stress_responses', function (Blueprint $table): void {
            $table->dropColumn('selected_option_ids');
        });
        Schema::table('stress_questions', function (Blueprint $table): void {
            $table->dropColumn(['answer_mode', 'max_selections', 'scoring_method']);
        });
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->dropColumn(['scoring_method', 'section_weighting', 'estimated_minutes']);
        });
    }
};
