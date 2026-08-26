<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_identity_id')->constrained()->cascadeOnDelete();
            $table->string('policy_version', 100);
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['student_identity_id', 'policy_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_consents');
    }
};
