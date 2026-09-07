<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-question scoring configuration for the dynamic wellbeing engine.
 * Every column is nullable or defaulted so existing questions keep their
 * current flat-additive behaviour until an admin opts in. `min_score` /
 * `max_score` stay null to mean "derive from the active option scores".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stress_questions', function (Blueprint $table): void {
            $table->integer('min_score')->nullable()->after('question_type');
            $table->integer('max_score')->nullable()->after('min_score');
            // "Controls how strongly this question contributes within its category."
            $table->decimal('wellbeing_weight', 6, 2)->default(1)->after('max_score');
            // "Use this when a higher answer should contribute less positively to wellbeing."
            $table->boolean('is_reverse_scored')->default(false)->after('wellbeing_weight');
            $table->boolean('stress_relevant')->default(false)->after('is_reverse_scored');
            // 'higher_more_stress' | 'higher_less_stress'
            $table->string('stress_direction', 30)->nullable()->after('stress_relevant');
            $table->decimal('stress_weight', 6, 2)->default(1)->after('stress_direction');
        });
    }

    public function down(): void
    {
        Schema::table('stress_questions', function (Blueprint $table): void {
            $table->dropColumn([
                'min_score', 'max_score', 'wellbeing_weight', 'is_reverse_scored',
                'stress_relevant', 'stress_direction', 'stress_weight',
            ]);
        });
    }
};
