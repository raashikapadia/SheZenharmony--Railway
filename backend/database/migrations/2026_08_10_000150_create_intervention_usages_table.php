<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stress_assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('intervention_id')->constrained()->cascadeOnDelete();
            $table->uuid('anonymous_session_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_usages');
    }
};
