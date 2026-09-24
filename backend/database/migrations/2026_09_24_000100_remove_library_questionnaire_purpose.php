<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the questionnaire `purpose` split.
 *
 * The app only ever had one questionnaire workflow in use: the baseline a
 * student takes from Stress Level. The 'library' purpose — independently
 * published questionnaires a student could choose to sit — was never
 * reachable from the app, so the column only distinguished rows nothing
 * treated differently. One live questionnaire at a time is now the rule,
 * enforced by activation rather than by a column.
 *
 * No questionnaire is deleted and no assessment history is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('questionnaires', 'purpose')) {
            return;
        }

        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->dropIndex('idx_questionnaires_purpose_availability');
        });

        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->dropColumn('purpose');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('questionnaires', 'purpose')) {
            return;
        }

        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->string('purpose', 20)->default('registration')->after('type');
            $table->index(['purpose', 'status', 'is_active'], 'idx_questionnaires_purpose_availability');
        });

        // Everything that survives the rollback is the baseline students take.
        DB::table('questionnaires')->update(['purpose' => 'registration']);
    }
};
