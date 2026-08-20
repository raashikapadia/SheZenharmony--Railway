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
        if (! Schema::hasColumn('stress_assessments', 'public_uuid')) {
            Schema::table('stress_assessments', function (Blueprint $table): void {
                $table->uuid('public_uuid')->nullable()->after('id');
            });
        }
        if (! Schema::hasIndex('stress_assessments', 'stress_assessments_public_uuid_unique')) {
            Schema::table('stress_assessments', function (Blueprint $table): void {
                $table->unique('public_uuid');
            });
        }

        if (! Schema::hasColumn('stress_assessments', 'stress_score_band_id')) {
            Schema::table('stress_assessments', function (Blueprint $table): void {
                $table->foreignId('stress_score_band_id')->nullable()->after('anonymous_session_fk');
            });
        }
        if (! Schema::hasForeignKey('stress_assessments', 'stress_assessments_stress_score_band_id_foreign')) {
            Schema::table('stress_assessments', function (Blueprint $table): void {
                $table->foreign('stress_score_band_id')->references('id')->on('stress_score_bands')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('stress_assessments', 'assessment_type')) {
            Schema::table('stress_assessments', function (Blueprint $table): void {
                $table->string('assessment_type', 50)->default('stress')->after('stress_score_band_id');
            });
        }
        if (! Schema::hasColumn('stress_assessments', 'assessment_status')) {
            Schema::table('stress_assessments', function (Blueprint $table): void {
                $table->string('assessment_status', 20)->default('in_progress')->after('assessment_type');
            });
        }
        if (! Schema::hasColumn('stress_assessments', 'started_at')) {
            Schema::table('stress_assessments', function (Blueprint $table): void {
                $table->timestamp('started_at')->nullable()->after('stress_level');
            });
        }

        foreach ([
            'stress_assessments_user_id_created_at_index' => ['user_id', 'created_at'],
            'stress_assessments_anonymous_session_fk_created_at_index' => ['anonymous_session_fk', 'created_at'],
            'stress_assessments_assessment_status_created_at_index' => ['assessment_status', 'created_at'],
        ] as $indexName => $columns) {
            if (! Schema::hasIndex('stress_assessments', $indexName)) {
                Schema::table('stress_assessments', function (Blueprint $table) use ($columns, $indexName): void {
                    $table->index($columns, $indexName);
                });
            }
        }

        DB::table('stress_assessments')->orderBy('id')->eachById(
            fn (object $assessment) => DB::table('stress_assessments')->where('id', $assessment->id)->update([
                'public_uuid' => (string) Str::uuid(),
                'assessment_status' => $assessment->completed_at === null ? 'in_progress' : 'completed',
                'started_at' => $assessment->created_at,
            ])
        );

        if (! Schema::hasColumn('stress_responses', 'answer_text')) {
            Schema::table('stress_responses', function (Blueprint $table): void {
                $table->text('answer_text')->nullable()->after('numeric_value');
            });
        }
        if (! Schema::hasColumn('stress_responses', 'question_text_snapshot')) {
            Schema::table('stress_responses', function (Blueprint $table): void {
                $table->text('question_text_snapshot')->nullable()->after('score');
            });
        }
        if (! Schema::hasColumn('stress_responses', 'option_text_snapshot')) {
            Schema::table('stress_responses', function (Blueprint $table): void {
                $table->string('option_text_snapshot', 500)->nullable()->after('question_text_snapshot');
            });
        }
        if (! Schema::hasIndex('stress_responses', 'stress_responses_stress_assessment_id_stress_question_id_unique')) {
            Schema::table('stress_responses', function (Blueprint $table): void {
                $table->unique(['stress_assessment_id', 'stress_question_id']);
            });
        }

        DB::table('stress_responses')->orderBy('id')->eachById(function (object $response): void {
            DB::table('stress_responses')->where('id', $response->id)->update([
                'question_text_snapshot' => DB::table('stress_questions')
                    ->where('id', $response->stress_question_id)->value('question_text'),
                'option_text_snapshot' => $response->question_option_id === null ? null : DB::table('question_options')
                    ->where('id', $response->question_option_id)->value('label'),
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('stress_responses', 'stress_responses_stress_assessment_id_stress_question_id_unique')) {
            Schema::table('stress_responses', function (Blueprint $table): void {
                $table->dropUnique('stress_responses_stress_assessment_id_stress_question_id_unique');
            });
        }
        foreach (['answer_text', 'question_text_snapshot', 'option_text_snapshot'] as $column) {
            if (Schema::hasColumn('stress_responses', $column)) {
                Schema::table('stress_responses', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }

        foreach ([
            'stress_assessments_assessment_status_created_at_index',
            'stress_assessments_anonymous_session_fk_created_at_index',
            'stress_assessments_user_id_created_at_index',
        ] as $indexName) {
            if (Schema::hasIndex('stress_assessments', $indexName)) {
                Schema::table('stress_assessments', fn (Blueprint $table) => $table->dropIndex($indexName));
            }
        }
        if (Schema::hasForeignKey('stress_assessments', 'stress_assessments_stress_score_band_id_foreign')) {
            Schema::table('stress_assessments', fn (Blueprint $table) => $table->dropForeign('stress_assessments_stress_score_band_id_foreign'));
        }
        if (Schema::hasIndex('stress_assessments', 'stress_assessments_public_uuid_unique')) {
            Schema::table('stress_assessments', fn (Blueprint $table) => $table->dropUnique('stress_assessments_public_uuid_unique'));
        }
        foreach (['stress_score_band_id', 'public_uuid', 'assessment_type', 'assessment_status', 'started_at'] as $column) {
            if (Schema::hasColumn('stress_assessments', $column)) {
                Schema::table('stress_assessments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
