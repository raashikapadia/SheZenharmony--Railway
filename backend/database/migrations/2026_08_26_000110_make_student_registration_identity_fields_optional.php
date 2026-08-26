<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
        });

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('user_profiles')
            ->whereNull('user_id')
            ->update([
                'user_id' => DB::raw('(SELECT user_id FROM student_identities WHERE student_identities.id = user_profiles.student_identity_id)'),
            ]);
        DB::table('users')->whereNull('name')->update(['name' => 'Student']);

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
        });
    }
};
