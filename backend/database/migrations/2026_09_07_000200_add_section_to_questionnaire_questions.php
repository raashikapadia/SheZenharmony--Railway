<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A question's section placement is per-questionnaire (a shared bank question
 * can sit in different sections in different questionnaires), so the link
 * lives on the pivot next to the existing `position` / `is_required`.
 * Nullable so every existing membership row stays valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaire_questions', function (Blueprint $table): void {
            $table->foreignId('questionnaire_section_id')->nullable()->after('questionnaire_id')
                ->constrained('questionnaire_sections')->nullOnDelete();
            $table->index(
                ['questionnaire_id', 'questionnaire_section_id', 'position'],
                'idx_questionnaire_questions_section_order',
            );
        });
    }

    public function down(): void
    {
        Schema::table('questionnaire_questions', function (Blueprint $table): void {
            $table->dropIndex('idx_questionnaire_questions_section_order');
            $table->dropConstrainedForeignId('questionnaire_section_id');
        });
    }
};
