<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stress_assessments', function (Blueprint $table): void {
            $table->uuid('public_uuid')->nullable()->unique()->after('id');
            $table->foreignId('stress_score_band_id')->nullable()->after('anonymous_session_fk')
                ->constrained()->nullOnDelete();
            $table->string('assessment_type', 50)->default('stress')->after('stress_score_band_id');
            $table->string('assessment_status', 20)->default('in_progress')->after('assessment_type');
            $table->timestamp('started_at')->nullable()->after('stress_level');
            $table->index(['user_id', 'created_at']);
            $table->index(['anonymous_session_fk', 'created_at']);
            $table->index(['assessment_status', 'created_at']);
        });

        DB::table('stress_assessments')->orderBy('id')->eachById(
            fn (object $assessment) => DB::table('stress_assessments')->where('id', $assessment->id)->update([
                'public_uuid' => (string) Str::uuid(),
                'assessment_status' => $assessment->completed_at === null ? 'in_progress' : 'completed',
                'started_at' => $assessment->created_at,
            ])
        );

        Schema::table('stress_responses', function (Blueprint $table): void {
            $table->text('answer_text')->nullable()->after('numeric_value');
            $table->text('question_text_snapshot')->nullable()->after('score');
            $table->string('option_text_snapshot', 500)->nullable()->after('question_text_snapshot');
            $table->unique(['stress_assessment_id', 'stress_question_id']);
        });

        DB::table('stress_responses')->orderBy('id')->eachById(function (object $response): void {
            DB::table('stress_responses')->where('id', $response->id)->update([
                'question_text_snapshot' => DB::table('stress_questions')
                    ->where('id', $response->stress_question_id)->value('question_text'),
                'option_text_snapshot' => $response->question_option_id === null ? null : DB::table('question_options')
                    ->where('id', $response->question_option_id)->value('label'),
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stress_assessments ADD CONSTRAINT chk_assessment_at_most_one_owner CHECK (user_id IS NULL OR anonymous_session_fk IS NULL)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stress_assessments DROP CHECK chk_assessment_at_most_one_owner');
        }
        Schema::table('stress_responses', function (Blueprint $table): void {
            $table->dropUnique(['stress_assessment_id', 'stress_question_id']);
            $table->dropColumn(['answer_text', 'question_text_snapshot', 'option_text_snapshot']);
        });
        Schema::table('stress_assessments', function (Blueprint $table): void {
            $table->dropIndex(['assessment_status', 'created_at']);
            $table->dropIndex(['anonymous_session_fk', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropConstrainedForeignId('stress_score_band_id');
            $table->dropUnique(['public_uuid']);
            $table->dropColumn(['public_uuid', 'assessment_type', 'assessment_status', 'started_at']);
        });
    }
};
