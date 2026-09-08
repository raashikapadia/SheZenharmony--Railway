<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the optional coping-strategy fields a guidance item can carry, so the
 * student toolkit can show a short tip up front and a fuller "learn more"
 * explanation underneath. Every column is nullable: existing guidance keeps
 * working exactly as it does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_guidance', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('type');
            $table->string('summary', 500)->nullable()->after('title');
            $table->string('when_it_helps', 500)->nullable()->after('summary');
            $table->text('steps')->nullable()->after('when_it_helps');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('steps');
            $table->foreignId('related_intervention_id')
                ->nullable()
                ->after('duration_minutes')
                ->constrained('interventions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_guidance', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('related_intervention_id');
            $table->dropColumn([
                'title',
                'summary',
                'when_it_helps',
                'steps',
                'duration_minutes',
            ]);
        });
    }
};
