<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dynamic-engine outputs recorded alongside the existing flat
 * `total_score` / `stress_score_band_id` / `stress_level` columns (which
 * keep meaning "the overall result" for current admin and Flutter
 * consumers). All nullable: only populated when the questionnaire has
 * sections configured. `config_snapshot` freezes the exact scoring rules
 * used so a historical result can be recomputed without touching live
 * configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stress_assessments', function (Blueprint $table): void {
            $table->decimal('overall_raw_score', 10, 2)->nullable()->after('total_score');
            $table->decimal('overall_weighted_score', 10, 2)->nullable()->after('overall_raw_score');
            $table->decimal('overall_max_weighted_score', 10, 2)->nullable()->after('overall_weighted_score');
            $table->decimal('overall_percentage', 6, 2)->nullable()->after('overall_max_weighted_score');
            $table->foreignId('wellbeing_result_band_id')->nullable()->after('overall_percentage')
                ->constrained('stress_score_bands')->nullOnDelete();
            $table->decimal('stress_score', 6, 2)->nullable()->after('wellbeing_result_band_id');
            $table->decimal('stress_percentage', 6, 2)->nullable()->after('stress_score');
            $table->foreignId('stress_result_band_id')->nullable()->after('stress_percentage')
                ->constrained('stress_score_bands')->nullOnDelete();
            $table->json('config_snapshot')->nullable()->after('stress_result_band_id');
        });
    }

    public function down(): void
    {
        Schema::table('stress_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('wellbeing_result_band_id');
            $table->dropConstrainedForeignId('stress_result_band_id');
            $table->dropColumn([
                'overall_raw_score', 'overall_weighted_score', 'overall_max_weighted_score',
                'overall_percentage', 'stress_score', 'stress_percentage', 'config_snapshot',
            ]);
        });
    }
};
