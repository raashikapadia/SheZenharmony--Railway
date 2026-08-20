<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('pseudonymous_uuid')->nullable()->unique()->after('role');
            $table->string('account_status', 20)->default('active')->index()->after('pseudonymous_uuid');
            $table->softDeletes();
        });

        DB::table('users')->whereNull('pseudonymous_uuid')->orderBy('id')->eachById(
            fn (object $user) => DB::table('users')->where('id', $user->id)->update([
                'pseudonymous_uuid' => (string) Str::uuid(),
            ])
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['pseudonymous_uuid']);
            $table->dropIndex(['account_status']);
            $table->dropColumn(['pseudonymous_uuid', 'account_status', 'deleted_at']);
        });
    }
};
