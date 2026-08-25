<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->string('period', 100)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->dropColumn('period');
        });
    }
};
