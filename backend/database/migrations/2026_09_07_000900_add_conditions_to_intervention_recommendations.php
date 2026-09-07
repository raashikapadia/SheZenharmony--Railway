<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a recommendation be conditioned on a low wellbeing category or a
 * named result level, in addition to the existing stress-band link. Columns
 * only in this pass; the matching service/UI arrive in a later pass, and the
 * existing `stress_score_band_id` path is unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intervention_recommendations', function (Blueprint $table): void {
            $table->foreignId('questionnaire_section_id')->nullable()->after('stress_score_band_id')
                ->constrained('questionnaire_sections')->nullOnDelete();
            $table->string('result_level', 60)->nullable()->after('questionnaire_section_id');
        });
    }

    public function down(): void
    {
        Schema::table('intervention_recommendations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('questionnaire_section_id');
            $table->dropColumn('result_level');
        });
    }
};
