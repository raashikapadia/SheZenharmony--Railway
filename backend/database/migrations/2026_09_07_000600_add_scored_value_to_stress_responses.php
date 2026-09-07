<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reverse-scoring / weighting adjusted value the engine actually used
 * for this response. Nullable — the existing integer `score` column (the
 * raw option score) is still written for every response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stress_responses', function (Blueprint $table): void {
            $table->decimal('scored_value', 10, 2)->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('stress_responses', function (Blueprint $table): void {
            $table->dropColumn('scored_value');
        });
    }
};
