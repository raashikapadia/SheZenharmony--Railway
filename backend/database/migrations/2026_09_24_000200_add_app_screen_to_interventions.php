<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where in the app a recommended intervention takes a student.
 *
 * Result ranges already carry the support an admin recommends. This records
 * which existing SheZen Harmony screen each piece of support belongs to, so
 * tapping a recommendation opens that feature instead of only offering an
 * external link. Nullable: support with no destination keeps its current
 * behaviour (external link, or the detail sheet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table): void {
            $table->string('app_screen', 40)->nullable()->after('external_url');
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table): void {
            $table->dropColumn('app_screen');
        });
    }
};
