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
        Schema::create('student_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('pseudonymous_uuid')->unique();
            $table->timestamps();
        });

        $studentRoleId = DB::table('roles')->where('slug', 'student')->value('id');
        $students = DB::table('users')
            ->when($studentRoleId !== null, fn ($query) => $query->where(function ($query) use ($studentRoleId): void {
                $query->where('role', 'student')->orWhereExists(function ($roles) use ($studentRoleId): void {
                    $roles->selectRaw('1')->from('user_roles')
                        ->whereColumn('user_roles.user_id', 'users.id')
                        ->where('user_roles.role_id', $studentRoleId);
                });
            }), fn ($query) => $query->where('role', 'student'))
            ->orderBy('id')
            ->get(['id', 'pseudonymous_uuid']);

        foreach ($students as $student) {
            DB::table('student_identities')->insert([
                'user_id' => $student->id,
                'pseudonymous_uuid' => $student->pseudonymous_uuid ?: (string) Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->foreignId('student_identity_id')->nullable()->unique()->after('user_id')
                ->constrained()->cascadeOnDelete();
        });
        Schema::table('stress_assessments', function (Blueprint $table): void {
            $table->foreignId('student_identity_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
            $table->index(['student_identity_id', 'created_at']);
        });
        Schema::table('intervention_usages', function (Blueprint $table): void {
            $table->foreignId('student_identity_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
            $table->index(['student_identity_id', 'started_at']);
        });
        Schema::table('progress_entries', function (Blueprint $table): void {
            $table->foreignId('student_identity_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
            $table->index(['student_identity_id', 'recorded_at']);
        });
        Schema::table('chat_sessions', function (Blueprint $table): void {
            $table->foreignId('student_identity_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();
            $table->index(['student_identity_id', 'started_at']);
        });
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->foreignId('sender_student_identity_id')->nullable()->after('sender_user_id')
                ->constrained('student_identities')->nullOnDelete();
        });

        foreach (['user_profiles', 'stress_assessments', 'intervention_usages', 'progress_entries', 'chat_sessions'] as $table) {
            DB::table($table)
                ->whereNotNull("{$table}.user_id")
                ->whereNull("{$table}.student_identity_id")
                ->update([
                    'student_identity_id' => DB::raw("(SELECT student_identities.id FROM student_identities WHERE student_identities.user_id = {$table}.user_id)"),
                ]);
        }

        DB::table('chat_messages')
            ->whereNotNull('sender_user_id')
            ->whereNull('sender_student_identity_id')
            ->update([
                'sender_student_identity_id' => DB::raw('(SELECT student_identities.id FROM student_identities WHERE student_identities.user_id = chat_messages.sender_user_id)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('chat_messages', fn (Blueprint $table) => $table->dropConstrainedForeignId('sender_student_identity_id'));
        foreach (['chat_sessions', 'progress_entries', 'intervention_usages', 'stress_assessments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->dropIndex(['student_identity_id', match ($table) {
                    'chat_sessions', 'intervention_usages' => 'started_at',
                    'progress_entries' => 'recorded_at',
                    default => 'created_at',
                }]);
                $blueprint->dropConstrainedForeignId('student_identity_id');
            });
        }
        Schema::table('user_profiles', fn (Blueprint $table) => $table->dropConstrainedForeignId('student_identity_id'));
        Schema::dropIfExists('student_identities');
    }
};
