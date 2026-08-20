<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nickname', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('employment_status', 100)->nullable();
            $table->string('relationship_status', 100)->nullable();
            $table->boolean('has_children')->nullable();
            $table->string('living_situation', 150)->nullable();
            $table->string('preferred_language', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
            $table->index('role_id');
        });

        Schema::create('user_mfa_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30);
            $table->text('secret_encrypted')->nullable();
            $table->text('recovery_codes_encrypted')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'method']);
            $table->index(['user_id', 'is_active']);
        });

        $now = now();
        foreach ([
            ['name' => 'Student', 'slug' => 'student'],
            ['name' => 'Administrator', 'slug' => 'admin'],
            ['name' => 'Moderator', 'slug' => 'moderator'],
            ['name' => 'Counsellor', 'slug' => 'counsellor'],
        ] as $role) {
            DB::table('roles')->updateOrInsert(['slug' => $role['slug']], $role + [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('users')->whereNotNull('role')->distinct()->pluck('role') as $legacyRole) {
            DB::table('roles')->updateOrInsert(['slug' => $legacyRole], [
                'name' => str($legacyRole)->headline()->toString(),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')->orderBy('id')->eachById(function (object $user) use ($now): void {
            $roleId = DB::table('roles')->where('slug', $user->role)->value('id');
            if ($roleId !== null) {
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mfa_methods');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('user_profiles');
    }
};
