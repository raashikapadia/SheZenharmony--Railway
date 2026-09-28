<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin assign a piece of Personal Guidance to a managed
 * {@see \App\Models\ContentCategory} (until now only wired to Interventions)
 * instead of typing a free-text category, and gives admins explicit control
 * over display order instead of relying on update time.
 *
 * Both columns are additive and nullable: the existing free-text `category`
 * column is untouched, so guidance never re-saved under the new field keeps
 * showing exactly as it does today (see `PersonalGuidance::categoryName()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_guidance', function (Blueprint $table): void {
            $table->foreignId('content_category_id')
                ->nullable()
                ->after('category')
                ->constrained('content_categories')
                ->nullOnDelete();
            $table->unsignedInteger('position')->default(0)->after('content_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('personal_guidance', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_category_id');
            $table->dropColumn('position');
        });
    }
};
