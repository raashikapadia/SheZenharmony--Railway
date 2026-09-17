<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a student types when their year of study is "Other". Kept beside
 * `year_of_study` rather than inside it so the fixed options still group
 * cleanly in analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('year_of_study_detail', 100)->nullable()->after('year_of_study');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropColumn('year_of_study_detail');
        });
    }
};
