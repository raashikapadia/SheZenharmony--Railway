<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_options', function (Blueprint $table): void {
            $table->dropForeign(['stress_question_id']);
            $table->foreign('stress_question_id')->references('id')->on('stress_questions')->restrictOnDelete();
        });
        Schema::table('stress_responses', function (Blueprint $table): void {
            $table->dropForeign(['stress_question_id']);
            $table->dropForeign(['question_option_id']);
            $table->foreign('stress_question_id')->references('id')->on('stress_questions')->restrictOnDelete();
            $table->foreign('question_option_id')->references('id')->on('question_options')->restrictOnDelete();
        });
        Schema::table('intervention_usages', function (Blueprint $table): void {
            $table->dropForeign(['intervention_id']);
            $table->foreign('intervention_id')->references('id')->on('interventions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('intervention_usages', function (Blueprint $table): void {
            $table->dropForeign(['intervention_id']);
            $table->foreign('intervention_id')->references('id')->on('interventions')->cascadeOnDelete();
        });
        Schema::table('stress_responses', function (Blueprint $table): void {
            $table->dropForeign(['stress_question_id']);
            $table->dropForeign(['question_option_id']);
            $table->foreign('stress_question_id')->references('id')->on('stress_questions')->cascadeOnDelete();
            $table->foreign('question_option_id')->references('id')->on('question_options')->nullOnDelete();
        });
        Schema::table('question_options', function (Blueprint $table): void {
            $table->dropForeign(['stress_question_id']);
            $table->foreign('stress_question_id')->references('id')->on('stress_questions')->cascadeOnDelete();
        });
    }
};
