<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stress_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stress_assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stress_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_option_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('numeric_value', 8, 2)->nullable();
            $table->integer('score')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stress_responses');
    }
};
