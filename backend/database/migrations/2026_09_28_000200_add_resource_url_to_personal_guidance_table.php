<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional external link an admin can attach to a piece of advice (e.g. a
 * helpline page or an article) — additive and nullable, so existing rows are
 * unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_guidance', function (Blueprint $table): void {
            $table->string('resource_url', 2048)->nullable()->after('steps');
        });
    }

    public function down(): void
    {
        Schema::table('personal_guidance', function (Blueprint $table): void {
            $table->dropColumn('resource_url');
        });
    }
};
