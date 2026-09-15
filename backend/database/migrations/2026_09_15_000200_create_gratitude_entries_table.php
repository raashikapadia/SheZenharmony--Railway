<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gratitude_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_identity_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('symbol', 20);
            $table->timestamps();
            $table->index(['student_identity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gratitude_entries');
    }
};
