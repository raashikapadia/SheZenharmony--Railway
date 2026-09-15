<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The client's fixed result scale (e.g. 0–40). A student's raw points total
 * is normalised into it, so the scale — and the result ranges written on
 * it — stay put however many questions the questionnaire has.
 *
 * Nullable: a questionnaire without a scale keeps reporting raw totals,
 * which is how the historical flat questionnaires behave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->integer('result_scale_min')->nullable()->after('period');
            $table->integer('result_scale_max')->nullable()->after('result_scale_min');
        });

        // Every sectioned questionnaire so far was written against a 0–40
        // result scale (the seeded instrument and the admin defaults), so
        // give it that scale explicitly.
        DB::table('questionnaires')
            ->whereNull('result_scale_max')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('questionnaire_sections')
                ->whereColumn('questionnaire_sections.questionnaire_id', 'questionnaires.id'))
            ->update(['result_scale_min' => 0, 'result_scale_max' => 40]);
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->dropColumn(['result_scale_min', 'result_scale_max']);
        });
    }
};
