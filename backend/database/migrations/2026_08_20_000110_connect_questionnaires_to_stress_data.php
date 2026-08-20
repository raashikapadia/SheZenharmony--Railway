<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stress_score_bands', function (Blueprint $table): void {
            // Nullable preserves any existing global bands until they can be assigned deliberately.
            $table->foreignId('questionnaire_id')->nullable()->after('id')
                ->constrained()->restrictOnDelete();
            $table->index(['questionnaire_id', 'is_active', 'position'], 'idx_score_bands_questionnaire_active');
            $table->dropUnique(['code']);
            $table->unique(['questionnaire_id', 'code'], 'uq_score_bands_questionnaire_code');
        });

        Schema::table('stress_assessments', function (Blueprint $table): void {
            // Nullable preserves historical assessments created before questionnaires existed.
            $table->foreignId('questionnaire_id')->nullable()->after('public_uuid')
                ->constrained()->restrictOnDelete();
            $table->index(['questionnaire_id', 'assessment_status'], 'idx_assessments_questionnaire_status');
        });
    }

    public function down(): void
    {
        Schema::table('stress_assessments', function (Blueprint $table): void {
            $table->dropIndex('idx_assessments_questionnaire_status');
            $table->dropConstrainedForeignId('questionnaire_id');
        });
        Schema::table('stress_score_bands', function (Blueprint $table): void {
            $table->dropUnique('uq_score_bands_questionnaire_code');
            $table->dropIndex('idx_score_bands_questionnaire_active');
            $table->dropConstrainedForeignId('questionnaire_id');
            $table->unique('code');
        });
    }
};
