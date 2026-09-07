<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('account_hold_reason')->nullable()->after('account_status');
            $table->timestamp('account_held_at')->nullable()->after('account_hold_reason');
            $table->foreignId('account_held_by_user_id')
                ->nullable()
                ->after('account_held_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('account_held_by_user_id');
            $table->dropColumn(['account_hold_reason', 'account_held_at']);
        });
    }
};
