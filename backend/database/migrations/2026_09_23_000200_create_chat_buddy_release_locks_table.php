<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('chat_buddy_release_locks', function (Blueprint $table): void { $table->id(); $table->string('name')->unique(); $table->timestamps(); }); DB::table('chat_buddy_release_locks')->insert(['name' => 'global', 'created_at' => now(), 'updated_at' => now()]); } public function down(): void { Schema::dropIfExists('chat_buddy_release_locks'); } };
