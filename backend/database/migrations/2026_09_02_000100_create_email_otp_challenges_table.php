<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_otp_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 20);
            $table->string('otp_hash');
            $table->string('device_name', 100);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'created_at']);
            $table->index(['expires_at', 'verified_at', 'invalidated_at'], 'email_otp_challenges_state_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_otp_challenges');
    }
};
