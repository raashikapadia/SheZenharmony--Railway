<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits questionnaires into the two purposes the app actually has.
 *
 *  - 'registration': the one mandatory baseline a student completes right
 *    after signing up. Exactly one family carries this purpose, and it is
 *    never offered in the list students choose from.
 *  - 'library': independently created, published, attempted and reported
 *    questionnaires a student may choose to sit at any time.
 *
 * `type` stays the version-family key — activation is already scoped by it,
 * so one live version per family keeps working untouched and a library
 * questionnaire being published no longer demotes the baseline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->string('purpose', 20)->default('library')->after('type');
            $table->index(['purpose', 'status', 'is_active'], 'idx_questionnaires_purpose_availability');
        });

        // Everything that exists today is the post-registration baseline:
        // it is what the mandatory gate has always served, and students'
        // historical assessments point at it.
        DB::table('questionnaires')->update(['purpose' => 'registration']);
    }

    public function down(): void
    {
        Schema::table('questionnaires', function (Blueprint $table): void {
            $table->dropIndex('idx_questionnaires_purpose_availability');
            $table->dropColumn('purpose');
        });
    }
};
