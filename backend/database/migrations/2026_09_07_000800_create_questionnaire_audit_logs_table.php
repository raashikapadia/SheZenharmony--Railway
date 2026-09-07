<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accountability trail for administrator changes to questionnaire
 * structure and scoring configuration (spec section 38). Written by
 * QuestionnaireAuditLogger from the Blade and JSON admin actions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('questionnaire_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('entity', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('description', 500);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['questionnaire_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_audit_logs');
    }
};
