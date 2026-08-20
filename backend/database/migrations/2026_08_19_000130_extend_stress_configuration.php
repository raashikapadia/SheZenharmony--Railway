<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stress_questions', function (Blueprint $table): void {
            $table->string('code', 50)->nullable()->unique()->after('id');
            $table->text('help_text')->nullable()->after('dimension');
            $table->boolean('is_required')->default(true)->after('position');
            $table->foreignId('created_by_user_id')->nullable()->after('is_sensitive')
                ->constrained('users')->nullOnDelete();
            $table->index(['is_active', 'position']);
        });

        DB::table('stress_questions')->orderBy('id')->eachById(
            fn (object $question) => DB::table('stress_questions')->where('id', $question->id)->update([
                'code' => 'question-'.$question->id,
            ])
        );

        Schema::table('question_options', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('position');
            $table->unique(['stress_question_id', 'value']);
            $table->index(['stress_question_id', 'is_active', 'position']);
        });

        Schema::create('stress_score_bands', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 100);
            $table->integer('min_score');
            $table->integer('max_score');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'position']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE stress_score_bands ADD CONSTRAINT chk_score_band_range CHECK (min_score <= max_score)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stress_score_bands');
        Schema::table('question_options', function (Blueprint $table): void {
            $table->dropIndex(['stress_question_id', 'is_active', 'position']);
            $table->dropUnique(['stress_question_id', 'value']);
            $table->dropColumn('is_active');
        });
        Schema::table('stress_questions', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'position']);
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'help_text', 'is_required']);
        });
    }
};
