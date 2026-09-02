<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_guidance_favourites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_identity_id')->constrained('student_identities')->cascadeOnDelete();
            $table->foreignId('personal_guidance_id')->constrained('personal_guidance')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_identity_id', 'personal_guidance_id'], 'personal_guidance_favourites_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_guidance_favourites');
    }
};
