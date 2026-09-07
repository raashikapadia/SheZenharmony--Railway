<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Score bands gain a `scope`: 'overall' (existing behaviour — the wellbeing
 * result ranges) or 'stress' (the separate stress result ranges). Existing
 * rows are backfilled to 'overall', and the per-questionnaire code uniqueness
 * is widened to include the scope so both range sets can coexist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stress_score_bands', function (Blueprint $table): void {
            $table->string('scope', 20)->default('overall')->after('questionnaire_id');
            $table->text('description')->nullable()->after('label');
            $table->text('harmony_message')->nullable()->after('description');
        });

        DB::table('stress_score_bands')->whereNull('scope')->update(['scope' => 'overall']);

        Schema::table('stress_score_bands', function (Blueprint $table): void {
            $table->dropUnique('uq_score_bands_questionnaire_code');
            $table->unique(['questionnaire_id', 'scope', 'code'], 'uq_score_bands_questionnaire_scope_code');
            $table->index(['questionnaire_id', 'scope', 'is_active', 'position'], 'idx_score_bands_scope_active');
        });
    }

    public function down(): void
    {
        Schema::table('stress_score_bands', function (Blueprint $table): void {
            $table->dropIndex('idx_score_bands_scope_active');
            $table->dropUnique('uq_score_bands_questionnaire_scope_code');
            $table->unique(['questionnaire_id', 'code'], 'uq_score_bands_questionnaire_code');
            $table->dropColumn(['scope', 'description', 'harmony_message']);
        });
    }
};
